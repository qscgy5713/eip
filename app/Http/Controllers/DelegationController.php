<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DelegationController extends Controller
{
    /**
     * 職務代理人管理頁面
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 我設定的代理人
        $myDelegations = Delegation::with('delegate.department')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        // 別人設定我為代理人的記錄
        $delegatedToMe = Delegation::with('user.department')
            ->where('delegate_id', $user->id)
            ->latest()
            ->get();

        // 可選為代理人的同仁名單 (排除自己)
        $availableDelegates = User::with('department')
            ->where('id', '!=', $user->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department_id', 'job_title', 'role']);

        return Inertia::render('Delegations/Index', [
            'myDelegations' => $myDelegations,
            'delegatedToMe' => $delegatedToMe,
            'availableDelegates' => $availableDelegates,
        ]);
    }

    /**
     * 新增職務代理人設定
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'delegate_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ((int)$value === (int)$user->id) {
                        $fail('代理人不能設定為自己。');
                    }
                },
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'end_date.after_or_equal' => '代理結束日期不可早於開始日期。',
        ]);

        $delegate = User::findOrFail($validated['delegate_id']);

        $delegation = Delegation::create([
            'user_id' => $user->id,
            'delegate_id' => $delegate->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'is_active' => true,
        ]);

        AuditLog::log(
            action: 'create_delegation',
            description: "設定同仁 {$delegate->name} 為職務代理人（{$delegation->start_date} ~ {$delegation->end_date}）",
            auditable: $delegation,
            details: [
                'delegate_id' => $delegate->id,
                'start_date' => $delegation->start_date,
                'end_date' => $delegation->end_date,
            ]
        );

        return redirect()->route('delegations.index')->with('success', "已成功指定 {$delegate->name} 為簽核職務代理人！");
    }

    /**
     * 切換啟用狀態
     */
    public function toggle(Request $request, Delegation $delegation): RedirectResponse
    {
        $user = $request->user();

        // 僅本人或管理員可切換
        if ($delegation->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, '您沒有權限修改此項代理設定。');
        }

        $delegation->update([
            'is_active' => !$delegation->is_active,
        ]);

        $stateText = $delegation->is_active ? '啟用' : '終止/停用';

        AuditLog::log(
            action: 'toggle_delegation',
            description: "{$stateText}了代理人設定（代理人：{$delegation->delegate?->name}）",
            auditable: $delegation
        );

        return redirect()->route('delegations.index')->with('success', "已{$stateText}職務代理設定。");
    }

    /**
     * 刪除代理設定
     */
    public function destroy(Request $request, Delegation $delegation): RedirectResponse
    {
        $user = $request->user();

        // 僅本人或管理員可刪除
        if ($delegation->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, '您沒有權限刪除此項代理設定。');
        }

        $delegateName = $delegation->delegate?->name ?? '同仁';
        $delegation->delete();

        AuditLog::log(
            action: 'delete_delegation',
            description: "刪除了對 {$delegateName} 的職務代理設定"
        );

        return redirect()->route('delegations.index')->with('success', "已刪除代理設定。");
    }
}
