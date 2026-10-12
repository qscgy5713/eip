<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Carbon\Carbon;

/**
 * 表單類月報:請假 / 加班 / 費用報銷 / 請購,依「已核准」單據統計。
 * 歸屬月份:請假用開始日期、加班用加班日期、報銷與請購用申請日期。
 * 資料量為企業內部表單,於 PHP 端依 JSON 欄位歸月(同時相容 PostgreSQL 與測試用 SQLite)。
 */
class FormReportService
{
    /** 申請日往前回溯月數(請假/加班可能提早申請) */
    private const LOOKBACK_MONTHS = 12;

    /**
     * @param  array<int>|null  $departmentIds  null = 全公司
     * @return array<string, mixed>
     */
    public function build(string $month, ?array $departmentIds): array
    {
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $formIds = Form::whereIn('code', ['LEAVE', 'OVERTIME', 'EXPENSE', 'PURCHASE'])->pluck('id', 'code');

        $users = User::with('department:id,name')
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->orderBy('department_id')->orderBy('name')
            ->get(['id', 'name', 'employee_no', 'department_id', 'status']);

        $requests = FormRequest::whereIn('user_id', $users->pluck('id'))
            ->whereIn('form_id', $formIds->values())
            ->where('status', 'approved')
            ->whereBetween('created_at', [$start->copy()->subMonths(self::LOOKBACK_MONTHS), $end])
            ->get(['id', 'form_id', 'user_id', 'data', 'created_at']);

        $codeById = $formIds->flip();
        $perUser = [];
        $leaveByType = [];
        $expenseByType = [];
        $totals = ['leave_days' => 0.0, 'overtime_hours' => 0.0, 'expense_amount' => 0.0, 'purchase_amount' => 0.0,
            'leave_count' => 0, 'overtime_count' => 0, 'expense_count' => 0, 'purchase_count' => 0];

        foreach ($requests as $req) {
            $data = $req->data ?? [];
            $code = $codeById[$req->form_id];
            $uid = $req->user_id;
            $perUser[$uid] ??= ['leave_days' => 0.0, 'overtime_hours' => 0.0, 'expense_amount' => 0.0, 'purchase_amount' => 0.0];

            $belongs = match ($code) {
                'LEAVE' => $this->inMonth($data['start_date'] ?? null, $start, $end),
                'OVERTIME' => $this->inMonth($data['overtime_date'] ?? null, $start, $end),
                default => $req->created_at->between($start, $end),
            };
            if (! $belongs) {
                continue;
            }

            switch ($code) {
                case 'LEAVE':
                    $days = (float) ($data['days'] ?? 0);
                    $type = trim((string) ($data['leave_type'] ?? '')) ?: '未填假別';
                    $perUser[$uid]['leave_days'] += $days;
                    $leaveByType[$type]['type'] = $type;
                    $leaveByType[$type]['count'] = ($leaveByType[$type]['count'] ?? 0) + 1;
                    $leaveByType[$type]['days'] = ($leaveByType[$type]['days'] ?? 0) + $days;
                    $totals['leave_days'] += $days;
                    $totals['leave_count']++;
                    break;
                case 'OVERTIME':
                    $hours = (float) ($data['hours'] ?? 0);
                    $perUser[$uid]['overtime_hours'] += $hours;
                    $totals['overtime_hours'] += $hours;
                    $totals['overtime_count']++;
                    break;
                case 'EXPENSE':
                    $amount = (float) ($data['amount'] ?? 0);
                    $type = trim((string) ($data['expense_type'] ?? '')) ?: '未填項目';
                    $perUser[$uid]['expense_amount'] += $amount;
                    $expenseByType[$type]['type'] = $type;
                    $expenseByType[$type]['count'] = ($expenseByType[$type]['count'] ?? 0) + 1;
                    $expenseByType[$type]['amount'] = ($expenseByType[$type]['amount'] ?? 0) + $amount;
                    $totals['expense_amount'] += $amount;
                    $totals['expense_count']++;
                    break;
                case 'PURCHASE':
                    $amount = (float) ($data['estimated_cost'] ?? 0);
                    $perUser[$uid]['purchase_amount'] += $amount;
                    $totals['purchase_amount'] += $amount;
                    $totals['purchase_count']++;
                    break;
            }
        }

        // 本月申請單的處理狀態分布(含未核准的)
        $statusCounts = FormRequest::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all();

        $rows = [];
        foreach ($users as $u) {
            $p = $perUser[$u->id] ?? null;
            if (! $p || array_sum($p) == 0.0) {
                continue;
            }
            $rows[] = [
                'user_id' => $u->id,
                'name' => $u->name,
                'employee_no' => $u->employee_no,
                'department' => $u->department?->name,
                'leave_days' => round($p['leave_days'], 2),
                'overtime_hours' => round($p['overtime_hours'], 2),
                'expense_amount' => round($p['expense_amount'], 2),
                'purchase_amount' => round($p['purchase_amount'], 2),
            ];
        }

        $byDesc = fn (string $key) => fn ($a, $b) => $b[$key] <=> $a[$key];
        $leaveList = array_values($leaveByType);
        usort($leaveList, $byDesc('days'));
        $expenseList = array_values($expenseByType);
        usort($expenseList, $byDesc('amount'));

        return [
            'month' => $month,
            'totals' => array_map(fn ($v) => round($v, 2), $totals),
            'leave_by_type' => $leaveList,
            'expense_by_type' => $expenseList,
            'status_counts' => [
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'approved' => (int) ($statusCounts['approved'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
                'revision_required' => (int) ($statusCounts['revision_required'] ?? 0),
                'withdrawn' => (int) ($statusCounts['withdrawn'] ?? 0),
            ],
            'rows' => $rows,
        ];
    }

    private function inMonth(mixed $date, Carbon $start, Carbon $end): bool
    {
        if (! is_string($date) || $date === '') {
            return false;
        }
        try {
            return Carbon::parse($date)->between($start, $end);
        } catch (\Throwable) {
            return false;
        }
    }
}
