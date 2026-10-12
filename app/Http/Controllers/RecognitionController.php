<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PointWallet;
use App\Models\RecognitionLike;
use App\Models\RecognitionPost;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RecognitionController extends Controller
{
    /**
     * 企業文化感謝牆、感謝幣錢包與月度感謝之星
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = PointWallet::getOrCreateForUser($user);

        $query = RecognitionPost::with(['sender:id,name', 'recipient:id,name', 'recipient.department:id,name']);

        if ($request->filled('core_value') && array_key_exists($request->core_value, RecognitionPost::CORE_VALUES)) {
            $query->where('core_value', $request->core_value);
        }

        // 頁籤：all(全體文化牆) / received(我收到的) / sent(我送出的)
        $tab = $request->input('tab', 'all');
        if ($tab === 'received') {
            $query->where('recipient_id', $user->id);
        } elseif ($tab === 'sent') {
            $query->where('sender_id', $user->id);
        }

        $posts = $query->latest('id')->paginate(10)->withQueryString();

        $likedIds = RecognitionLike::where('user_id', $user->id)
            ->whereIn('recognition_post_id', $posts->pluck('id'))
            ->pluck('recognition_post_id')
            ->flip();

        // 匿名貼文僅送出者本人看得到寄件者，其餘一律隱藏 (含管理員)
        $posts->getCollection()->transform(function (RecognitionPost $post) use ($user, $likedIds) {
            $isMine = $post->sender_id === $user->id;
            $post->setRelation('sender', ($post->is_anonymous && ! $isMine) ? null : $post->sender);
            $post->setAttribute('liked_by_me', $likedIds->has($post->id));
            $post->setAttribute('can_delete', $isMine || $user->isAdmin());
            if ($post->is_anonymous && ! $isMine) {
                $post->makeHidden('sender_id');
            }

            return $post;
        });

        // 本月感謝之星 Top 5 (依收到的感謝幣總數)
        $stars = RecognitionPost::query()
            ->select('recipient_id', DB::raw('SUM(points) as total_points'), DB::raw('COUNT(*) as thanks_count'))
            ->where('created_at', '>=', now()->startOfMonth())
            ->groupBy('recipient_id')
            ->orderByDesc('total_points')
            ->orderBy('recipient_id')
            ->limit(5)
            ->with('recipient:id,name', 'recipient.department:id,name')
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->recipient_id,
                'name' => $row->recipient?->name,
                'department' => $row->recipient?->department?->name,
                'total_points' => (int) $row->total_points,
                'thanks_count' => (int) $row->thanks_count,
            ]);

        $recipients = User::with('department:id,name')
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'department_id'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'department' => $u->department?->name,
            ]);

        return Inertia::render('Recognition/Index', [
            'posts' => $posts,
            'wallet' => [
                'recognition_quota' => $wallet->recognition_quota,
                'reward_points' => $wallet->reward_points,
                'monthly_quota' => PointWallet::MONTHLY_QUOTA,
            ],
            'stars' => $stars,
            'recipients' => $recipients,
            'coreValues' => RecognitionPost::CORE_VALUES,
            'filters' => [
                'tab' => $tab,
                'core_value' => $request->input('core_value', ''),
            ],
        ]);
    }

    /**
     * 贈送感謝幣並發佈至文化牆
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'points' => ['required', 'integer', 'min:1', 'max:50'],
            'core_value' => ['required', 'string', 'in:' . implode(',', array_keys(RecognitionPost::CORE_VALUES))],
            'message' => ['required', 'string', 'max:500'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        if ((int) $validated['recipient_id'] === $user->id) {
            throw ValidationException::withMessages(['recipient_id' => '不能將感謝幣贈送給自己']);
        }

        $recipient = User::findOrFail($validated['recipient_id']);
        if ($recipient->status !== 'active') {
            throw ValidationException::withMessages(['recipient_id' => '對象非在職同仁，無法贈送感謝幣']);
        }

        $post = DB::transaction(function () use ($user, $recipient, $validated) {
            PointWallet::getOrCreateForUser($user);
            PointWallet::getOrCreateForUser($recipient);

            // 固定依 user_id 排序加鎖，避免互贈時雙向死鎖
            $wallets = PointWallet::whereIn('user_id', [$user->id, $recipient->id])
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            $senderWallet = $wallets[$user->id]->refreshQuotaForCurrentMonth();
            $recipientWallet = $wallets[$recipient->id];

            if ($senderWallet->recognition_quota < $validated['points']) {
                throw ValidationException::withMessages([
                    'points' => "本月感謝幣額度不足，剩餘 {$senderWallet->recognition_quota} 枚",
                ]);
            }

            $senderWallet->decrement('recognition_quota', $validated['points']);
            $recipientWallet->increment('reward_points', $validated['points']);

            return RecognitionPost::create([
                'sender_id' => $user->id,
                'recipient_id' => $recipient->id,
                'points' => $validated['points'],
                'core_value' => $validated['core_value'],
                'message' => $validated['message'],
                'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
            ]);
        });

        $senderLabel = $post->is_anonymous ? '一位匿名同仁' : $user->name;
        $recipient->notify(new EipSystemNotification(
            title: '【感謝幣入帳】您收到一份感謝',
            message: "{$senderLabel} 因「{$post->core_value_label}」贈送您 {$post->points} 枚感謝幣：{$post->message}",
            type: 'recognition',
            actionUrl: route('recognition.index', ['tab' => 'received']),
            senderName: $post->is_anonymous ? '企業文化牆' : $user->name
        ));

        AuditLog::log(
            'recognition_give',
            "贈送 {$recipient->name} {$post->points} 枚感謝幣",
            $post,
            ['recipient_id' => $recipient->id, 'points' => $post->points, 'core_value' => $post->core_value]
        );

        return back()->with('success', "已送出 {$post->points} 枚感謝幣給 {$recipient->name}！");
    }

    /**
     * 點讚 / 取消點讚 (Toggle)
     */
    public function like(Request $request, RecognitionPost $recognitionPost): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $recognitionPost) {
            $existing = RecognitionLike::where('recognition_post_id', $recognitionPost->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
            } else {
                RecognitionLike::create([
                    'recognition_post_id' => $recognitionPost->id,
                    'user_id' => $user->id,
                ]);
            }

            $recognitionPost->update([
                'likes_count' => RecognitionLike::where('recognition_post_id', $recognitionPost->id)->count(),
            ]);
        });

        return back();
    }

    /**
     * 刪除貼文 (送出者本人或管理員；已入帳點數不追回)
     */
    public function destroy(Request $request, RecognitionPost $recognitionPost): RedirectResponse
    {
        $user = $request->user();

        if ($recognitionPost->sender_id !== $user->id && ! $user->isAdmin()) {
            abort(403, '僅貼文送出者或管理員可刪除此感謝貼文');
        }

        AuditLog::log(
            'recognition_delete',
            "刪除感謝牆貼文 #{$recognitionPost->id}",
            $recognitionPost,
            ['sender_id' => $recognitionPost->sender_id, 'recipient_id' => $recognitionPost->recipient_id]
        );

        $recognitionPost->delete();

        return back()->with('success', '感謝貼文已刪除（已贈送的感謝幣不予追回）。');
    }
}
