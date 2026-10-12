<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PointWallet;
use App\Models\WelfareRedemption;
use App\Models\WelfareReward;
use App\Notifications\EipSystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WelfareController extends Controller
{
    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user->isAdmin() || $user->isHr();
    }

    /**
     * 福利點數存摺、福利商城與兌換紀錄
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = PointWallet::getOrCreateForUser($user);
        $canManage = $this->canManage($request);

        $rewards = WelfareReward::query()
            ->when(! $canManage, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('points_cost')
            ->get();

        $myRedemptions = WelfareRedemption::with('reward:id,name,category')
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(30)
            ->get();

        $manageQueue = $canManage
            ? WelfareRedemption::with(['user:id,name', 'user.department:id,name', 'reward:id,name'])
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
                ->latest('id')
                ->limit(50)
                ->get()
            : [];

        return Inertia::render('Welfare/Index', [
            'wallet' => [
                'reward_points' => $wallet->reward_points,
                'recognition_quota' => $wallet->recognition_quota,
            ],
            'rewards' => $rewards,
            'myRedemptions' => $myRedemptions,
            'manageQueue' => $manageQueue,
            'categories' => WelfareReward::CATEGORIES,
            'canManage' => $canManage,
            'filters' => ['tab' => $request->input('tab', 'shop')],
        ]);
    }

    /**
     * 上架新福利商品 (HR / 管理員)
     */
    public function storeReward(Request $request): RedirectResponse
    {
        if (! $this->canManage($request)) {
            abort(403, '僅人資或系統管理人員可管理福利商城');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'points_cost' => ['required', 'integer', 'min:1', 'max:100000'],
            'stock' => ['required', 'integer', 'min:-1', 'max:100000'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(WelfareReward::CATEGORIES))],
        ]);

        $reward = WelfareReward::create($validated + ['is_active' => true]);

        AuditLog::log('welfare_reward_create', "上架福利商品《{$reward->name}》", $reward, [
            'points_cost' => $reward->points_cost,
            'stock' => $reward->stock,
        ]);

        return back()->with('success', "福利商品《{$reward->name}》已上架！");
    }

    /**
     * 上 / 下架福利商品
     */
    public function toggleReward(Request $request, WelfareReward $welfareReward): RedirectResponse
    {
        if (! $this->canManage($request)) {
            abort(403, '僅人資或系統管理人員可管理福利商城');
        }

        $welfareReward->update(['is_active' => ! $welfareReward->is_active]);

        AuditLog::log(
            'welfare_reward_toggle',
            ($welfareReward->is_active ? '上架' : '下架') . "福利商品《{$welfareReward->name}》",
            $welfareReward
        );

        return back()->with('success', '商品已' . ($welfareReward->is_active ? '上架' : '下架') . '。');
    }

    /**
     * 同仁以福利點數兌換商品 (悲觀鎖防超賣與重複扣點)
     */
    public function redeem(Request $request, WelfareReward $welfareReward): RedirectResponse
    {
        $user = $request->user();

        $redemption = DB::transaction(function () use ($user, $welfareReward) {
            PointWallet::getOrCreateForUser($user);

            $wallet = PointWallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $reward = WelfareReward::whereKey($welfareReward->id)->lockForUpdate()->firstOrFail();

            if (! $reward->is_active) {
                throw ValidationException::withMessages(['error' => '此商品已下架，無法兌換']);
            }

            if ($reward->stock === 0) {
                throw ValidationException::withMessages(['error' => '此商品已兌換完畢']);
            }

            if ($wallet->reward_points < $reward->points_cost) {
                throw ValidationException::withMessages([
                    'error' => "福利點數不足，需要 {$reward->points_cost} 點，目前僅有 {$wallet->reward_points} 點",
                ]);
            }

            $wallet->decrement('reward_points', $reward->points_cost);

            if ($reward->stock > 0) {
                $reward->decrement('stock');
            }

            return WelfareRedemption::create([
                'redemption_code' => WelfareRedemption::generateCode(),
                'user_id' => $user->id,
                'welfare_reward_id' => $reward->id,
                'points_spent' => $reward->points_cost,
                'status' => 'pending',
            ]);
        });

        AuditLog::log(
            'welfare_redeem',
            "兌換福利商品《{$welfareReward->name}》({$redemption->redemption_code})",
            $redemption,
            ['points_spent' => $redemption->points_spent]
        );

        return back()->with('success', "兌換成功！序號 {$redemption->redemption_code}，請待人資發放。");
    }

    /**
     * 人資核銷發放兌換品
     */
    public function fulfill(Request $request, WelfareRedemption $welfareRedemption): RedirectResponse
    {
        if (! $this->canManage($request)) {
            abort(403, '僅人資或系統管理人員可核銷發放福利');
        }

        if ($welfareRedemption->status !== 'pending') {
            return back()->withErrors(['error' => '此兌換單已處理完畢，無法重複核銷']);
        }

        $welfareRedemption->update([
            'status' => 'fulfilled',
            'fulfilled_at' => now(),
        ]);

        $welfareRedemption->load('reward:id,name');
        $welfareRedemption->user->notify(new EipSystemNotification(
            title: '【福利發放】兌換品已核發',
            message: "您兌換的《{$welfareRedemption->reward->name}》({$welfareRedemption->redemption_code}) 已由人資核發，請查收。",
            type: 'welfare',
            actionUrl: route('welfare.index', ['tab' => 'mine']),
            senderName: '人資福利中心'
        ));

        AuditLog::log(
            'welfare_fulfill',
            "核銷發放兌換單 {$welfareRedemption->redemption_code}",
            $welfareRedemption
        );

        return back()->with('success', "兌換單 {$welfareRedemption->redemption_code} 已核銷發放。");
    }

    /**
     * 取消兌換並退還點數與庫存 (本人於待發放時，或人資/管理員)
     */
    public function cancel(Request $request, WelfareRedemption $welfareRedemption): RedirectResponse
    {
        $user = $request->user();

        if ($welfareRedemption->user_id !== $user->id && ! $this->canManage($request)) {
            abort(403, '無權限取消此兌換單');
        }

        DB::transaction(function () use ($welfareRedemption) {
            $redemption = WelfareRedemption::whereKey($welfareRedemption->id)->lockForUpdate()->firstOrFail();

            if ($redemption->status !== 'pending') {
                throw ValidationException::withMessages(['error' => '僅待發放的兌換單可取消']);
            }

            $redemption->update(['status' => 'cancelled']);

            PointWallet::getOrCreateForUser($redemption->user);
            PointWallet::where('user_id', $redemption->user_id)->lockForUpdate()->first()
                ->increment('reward_points', $redemption->points_spent);

            $reward = WelfareReward::whereKey($redemption->welfare_reward_id)->lockForUpdate()->first();
            if ($reward && $reward->stock >= 0) {
                $reward->increment('stock');
            }
        });

        AuditLog::log(
            'welfare_cancel',
            "取消兌換單 {$welfareRedemption->redemption_code} 並退點",
            $welfareRedemption,
            ['points_refunded' => $welfareRedemption->points_spent]
        );

        if ($welfareRedemption->user_id !== $user->id) {
            $welfareRedemption->user->notify(new EipSystemNotification(
                title: '【福利兌換取消】點數已退還',
                message: "兌換單 {$welfareRedemption->redemption_code} 已由人資取消，{$welfareRedemption->points_spent} 點已退回您的福利存摺。",
                type: 'welfare',
                actionUrl: route('welfare.index', ['tab' => 'mine']),
                senderName: '人資福利中心'
            ));
        }

        return back()->with('success', '兌換單已取消，福利點數已退還。');
    }
}
