<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FormRequest;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    /**
     * 獲取同仁指定年份的所有假別額度（若尚未建立則自動初始化標準模板）
     */
    public function getUserBalances(User $user, ?int $year = null): Collection
    {
        $year = $year ?: (int) date('Y');

        $balances = LeaveBalance::where('user_id', $user->id)
            ->where('year', $year)
            ->get();

        if ($balances->isEmpty()) {
            $this->initUserBalances($user, $year);
            $balances = LeaveBalance::where('user_id', $user->id)
                ->where('year', $year)
                ->get();
        }

        // 依自訂排序呈現 (特休 -> 補休 -> 病假 -> 事假 -> 婚喪假 -> 公假)
        $order = [
            LeaveBalance::TYPE_ANNUAL => 1,
            LeaveBalance::TYPE_COMPENSATORY => 2,
            LeaveBalance::TYPE_SICK => 3,
            LeaveBalance::TYPE_PERSONAL => 4,
            LeaveBalance::TYPE_MARRIAGE_FUNERAL => 5,
            LeaveBalance::TYPE_OFFICIAL => 6,
        ];

        return $balances->sortBy(fn($b) => $order[$b->leave_type] ?? 99)->values();
    }

    /**
     * 為單一同仁初始化年度標準假別額度
     */
    public function initUserBalances(User $user, int $year): void
    {
        foreach (LeaveBalance::LEAVE_TYPES as $typeKey => $meta) {
            LeaveBalance::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'year' => $year,
                    'leave_type' => $typeKey,
                ],
                [
                    'allocated_days' => $meta['default_allocated'],
                    'used_days' => 0.0,
                    'pending_days' => 0.0,
                    'note' => $meta['description'] ?? null,
                ]
            );
        }
    }

    /**
     * 檢查同仁欲請假別之剩餘額度是否足夠
     */
    public function checkAvailability(User $user, string $rawType, float $days, ?int $year = null): array
    {
        $year = $year ?: (int) date('Y');
        $type = LeaveBalance::normalizeType($rawType);

        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id' => $user->id,
                'year' => $year,
                'leave_type' => $type,
            ],
            [
                'allocated_days' => LeaveBalance::LEAVE_TYPES[$type]['default_allocated'] ?? 0.0,
                'used_days' => 0.0,
                'pending_days' => 0.0,
                'note' => '系統自動初始化',
            ]
        );

        $available = $balance->available_days;
        $isHardQuota = $balance->is_hard_quota;
        $typeLabel = $balance->type_label;

        // 硬性管制假別（特休假、補休）：可用天數小於欲申請天數則不予送出
        if ($isHardQuota && $available < $days) {
            return [
                'allowed' => false,
                'is_hard_quota' => true,
                'available_days' => $available,
                'allocated_days' => (float) $balance->allocated_days,
                'requested_days' => $days,
                'type_label' => $typeLabel,
                'message' => "「{$typeLabel}」可用額度不足（可用剩餘 {$available} 天，欲申請 {$days} 天）。請調整申請天數或更換假別。",
            ];
        }

        // 軟性上限假別（病假、事假）：若超過年度基準天數，給予警示提醒
        $exceeded = false;
        $warning = null;
        $accumulated = (float) $balance->used_days + (float) $balance->pending_days + $days;
        if (!$isHardQuota && $accumulated > (float) $balance->allocated_days) {
            $exceeded = true;
            $warning = "提醒：「{$typeLabel}」累積申請將達 {$accumulated} 天，已超過常規年度額度（{$balance->allocated_days} 天）。";
        }

        return [
            'allowed' => true,
            'is_hard_quota' => $isHardQuota,
            'is_exceeded' => $exceeded,
            'warning' => $warning,
            'available_days' => $available,
            'allocated_days' => (float) $balance->allocated_days,
            'requested_days' => $days,
            'type_label' => $typeLabel,
            'message' => '額度檢核通過',
        ];
    }

    /**
     * 申請單送出時，扣留凍結額度 (Pending)
     */
    public function holdBalance(FormRequest $formRequest): bool
    {
        if ($formRequest->form?->code !== 'LEAVE') {
            return false;
        }

        $data = $formRequest->data ?? [];
        $rawType = $data['leave_type'] ?? '';
        $days = (float) ($data['days'] ?? 0);

        if (empty($rawType) || $days <= 0) {
            return false;
        }

        $type = LeaveBalance::normalizeType($rawType);
        $year = (int) date('Y', strtotime($data['start_date'] ?? 'now'));

        return DB::transaction(function () use ($formRequest, $year, $type, $days) {
            $balance = LeaveBalance::where('user_id', $formRequest->user_id)
                ->where('year', $year)
                ->where('leave_type', $type)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                $balance = LeaveBalance::create([
                    'user_id' => $formRequest->user_id,
                    'year' => $year,
                    'leave_type' => $type,
                    'allocated_days' => LeaveBalance::LEAVE_TYPES[$type]['default_allocated'] ?? 0.0,
                    'used_days' => 0.0,
                    'pending_days' => 0.0,
                ]);
            }

            // 並發排他校驗：硬性管制假別在資料庫鎖定態進行嚴格額度二次檢核 (SEC-05)
            if ($balance->is_hard_quota && $balance->available_days < $days) {
                throw new \InvalidArgumentException("「{$balance->type_label}」可用額度不足（可用剩餘 {$balance->available_days} 天，欲申請 {$days} 天）。");
            }

            $balance->increment('pending_days', $days);
            return true;
        });
    }

    /**
     * 流程結案時釋放或轉化額度 (Approved => Used; Rejected => Released)
     */
    public function releaseBalance(FormRequest $formRequest, bool $approved = true): bool
    {
        if ($formRequest->form?->code !== 'LEAVE') {
            return false;
        }

        $data = $formRequest->data ?? [];
        $rawType = $data['leave_type'] ?? '';
        $days = (float) ($data['days'] ?? 0);

        if (empty($rawType) || $days <= 0) {
            return false;
        }

        $type = LeaveBalance::normalizeType($rawType);
        $year = (int) date('Y', strtotime($data['start_date'] ?? 'now'));

        $balance = LeaveBalance::where('user_id', $formRequest->user_id)
            ->where('year', $year)
            ->where('leave_type', $type)
            ->first();

        if (!$balance) {
            return false;
        }

        DB::transaction(function () use ($balance, $days, $approved) {
            $newPending = max(0.0, (float) $balance->pending_days - $days);
            $balance->pending_days = $newPending;

            if ($approved) {
                $balance->used_days = (float) $balance->used_days + $days;
            }

            $balance->save();
        });

        return true;
    }

    /**
     * HR / 管理員調整指定同仁之假別配額
     */
    public function updateQuota(
        User $targetUser,
        int $year,
        string $rawType,
        float $allocatedDays,
        ?string $note = null,
        ?User $operator = null
    ): LeaveBalance {
        $type = LeaveBalance::normalizeType($rawType);

        $balance = LeaveBalance::firstOrNew([
            'user_id' => $targetUser->id,
            'year' => $year,
            'leave_type' => $type,
        ]);

        $oldAllocated = (float) $balance->allocated_days;
        $balance->allocated_days = $allocatedDays;
        if ($note !== null) {
            $balance->note = $note;
        }
        $balance->save();

        if ($operator) {
            AuditLog::log(
                action: 'update_leave_quota',
                description: "管理者 {$operator->name} 調整了同仁 {$targetUser->name} 的 {$year} 年度「{$balance->type_label}」額度（原：{$oldAllocated} 天 ➜ 新：{$allocatedDays} 天）",
                auditable: $balance,
                details: [
                    'target_user_id' => $targetUser->id,
                    'year' => $year,
                    'leave_type' => $type,
                    'old_allocated' => $oldAllocated,
                    'new_allocated' => $allocatedDays,
                    'note' => $note,
                ]
            );
        }

        return $balance;
    }

    /**
     * 批次為全公司或特定部門同仁初始化年度假別
     */
    public function batchInitYear(int $year, ?int $departmentId = null, ?User $operator = null): int
    {
        $query = User::query();
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $users = $query->get();
        $count = 0;

        foreach ($users as $user) {
            $this->initUserBalances($user, $year);
            $count++;
        }

        if ($operator) {
            AuditLog::log(
                action: 'batch_init_leave_balances',
                description: "管理者 {$operator->name} 執行了 {$year} 年度休假額度批次初始化（共處理 {$count} 名同仁）",
                details: [
                    'year' => $year,
                    'department_id' => $departmentId,
                    'users_count' => $count,
                ]
            );
        }

        return $count;
    }

    /**
     * 加班申請單 (OVERTIME) 核准結案時，若選擇「換取補休時數」，自動折算補休額度入帳
     */
    public function creditCompensatoryLeave(FormRequest $formRequest): ?LeaveBalance
    {
        if ($formRequest->form?->code !== 'OVERTIME') {
            return null;
        }

        $data = $formRequest->data ?? [];
        $compensation = $data['compensation'] ?? '';

        // 僅當補償方式為換取補休時處理
        if (!str_contains($compensation, '補休')) {
            return null;
        }

        $hours = floatval($data['hours'] ?? 0);
        if ($hours <= 0) {
            return null;
        }

        $user = $formRequest->user;
        if (!$user) {
            return null;
        }

        // 以法定 8 小時 = 1 天標準工時進行折算（例如 4 小時 = 0.5 天）
        $creditDays = round($hours / 8.0, 2);
        $overtimeDate = $data['overtime_date'] ?? date('Y-m-d');
        $year = (int) date('Y', strtotime($overtimeDate));

        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id' => $user->id,
                'year' => $year,
                'leave_type' => LeaveBalance::TYPE_COMPENSATORY,
            ],
            [
                'allocated_days' => 0.0,
                'used_days' => 0.0,
                'pending_days' => 0.0,
                'note' => '系統補休帳戶',
            ]
        );

        $oldAllocated = (float) $balance->allocated_days;
        $newAllocated = $oldAllocated + $creditDays;
        $balance->allocated_days = $newAllocated;

        $noteMsg = "[加班單 #{$formRequest->id} 核准入帳 +{$creditDays}天]";
        $balance->note = $balance->note ? "{$balance->note}；{$noteMsg}" : $noteMsg;
        $balance->save();

        AuditLog::log(
            action: 'overtime_compensatory_credited',
            description: "加班單「{$formRequest->title}」核准結案，系統自動為同仁 {$user->name} 折算入帳 {$creditDays} 天補休額度（加班 {$hours} 小時）",
            auditable: $balance,
            details: [
                'form_request_id' => $formRequest->id,
                'user_id' => $user->id,
                'year' => $year,
                'overtime_date' => $overtimeDate,
                'overtime_hours' => $hours,
                'credit_days' => $creditDays,
                'old_allocated' => $oldAllocated,
                'new_allocated' => $newAllocated,
            ]
        );

        $user->notify(new \App\Notifications\EipSystemNotification(
            title: "【補休入帳通知】加班單 #{$formRequest->id} 已核准並折算補休",
            message: "您於 {$overtimeDate} 之加班申請（共 {$hours} 小時）已全數審核通過，系統已自動折算 {$creditDays} 天補休額度入帳至您的 {$year} 年度休假帳戶！",
            type: 'form_approval',
            actionUrl: route('leave-balances.index'),
            senderName: '系統自動入帳'
        ));

        return $balance;
    }
}
