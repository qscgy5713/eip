<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayslipController extends Controller
{
    /**
     * 薪資單清單列表與管理總覽
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $canManage = $user->isHr() || $user->isAdmin();
        $view = $request->input('view', 'my'); // 'my' 或 'manage'
        if ($view === 'manage' && ! $canManage) {
            $view = 'my';
        }

        $now = Carbon::now();
        $selectedYear = (int) $request->input('year', $now->year);
        $selectedMonth = $request->filled('month') ? (int) $request->month : null;

        if ($view === 'manage') {
            // HR / 管理員專屬：全員薪資單管理看板
            $query = Payslip::with(['user.department']);

            if ($request->filled('year')) {
                $query->where('year', $selectedYear);
            }
            if ($selectedMonth) {
                $query->where('month', $selectedMonth);
            }
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            if ($request->filled('department_id')) {
                $query->whereHas('user', fn ($uq) => $uq->where('department_id', $request->department_id));
            }
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                      ->orWhere('employee_no', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $payslips = $query->orderByDesc('year')
                ->orderByDesc('month')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();

            // 當前篩選條件之統計彙總
            $statsQuery = clone $query;
            $statsPayslips = $statsQuery->get(['net_pay', 'total_earnings', 'total_deductions', 'status']);
            $manageStats = [
                'total_count' => $statsPayslips->count(),
                'published_count' => $statsPayslips->where('status', 'published')->count(),
                'draft_count' => $statsPayslips->where('status', 'draft')->count(),
                'total_net_pay' => (float) $statsPayslips->sum('net_pay'),
                'total_gross_pay' => (float) $statsPayslips->sum('total_earnings'),
            ];

            $departments = Department::orderBy('name')->get(['id', 'name']);
            $users = User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'employee_no', 'email', 'job_title']);

            return Inertia::render('Payslips/Index', [
                'payslips' => $payslips,
                'view' => 'manage',
                'canManage' => true,
                'manageStats' => $manageStats,
                'departments' => $departments,
                'users' => $users,
                'filters' => [
                    'view' => 'manage',
                    'year' => $selectedYear,
                    'month' => $selectedMonth,
                    'status' => $request->status ?? 'all',
                    'department_id' => $request->department_id ?? '',
                    'search' => $request->search ?? '',
                ],
            ]);
        }

        // 一般同仁：我的薪資明細存摺 (僅查已發布 published)
        $query = Payslip::where('user_id', $user->id)
            ->where('status', 'published');

        if ($request->filled('year')) {
            $query->where('year', $selectedYear);
        }

        $payslips = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12)
            ->withQueryString();

        // 個人年度統計
        $myYearPayslips = Payslip::where('user_id', $user->id)
            ->where('year', $selectedYear)
            ->where('status', 'published')
            ->get(['net_pay', 'total_earnings', 'total_deductions']);

        $myStats = [
            'year' => $selectedYear,
            'annual_net_total' => (float) $myYearPayslips->sum('net_pay'),
            'annual_gross_total' => (float) $myYearPayslips->sum('total_earnings'),
            'annual_deductions_total' => (float) $myYearPayslips->sum('total_deductions'),
            'count' => $myYearPayslips->count(),
            'average_monthly_net' => $myYearPayslips->count() > 0 ? round($myYearPayslips->avg('net_pay'), 2) : 0,
        ];

        return Inertia::render('Payslips/Index', [
            'payslips' => $payslips,
            'view' => 'my',
            'canManage' => $canManage,
            'myStats' => $myStats,
            'filters' => [
                'view' => 'my',
                'year' => $selectedYear,
            ],
        ]);
    }

    /**
     * 薪資單明細與列印檢視 (含 IDOR 防護)
     */
    public function show(Payslip $payslip, Request $request)
    {
        $user = $request->user();

        if (! $payslip->canAccess($user)) {
            abort(403, '您沒有權限調閱此筆機密薪資單據');
        }

        $payslip->load(['user.department']);

        AuditLog::log(
            'payslip_view',
            "調閱 {$payslip->user->name} {$payslip->period_label} 薪資單",
            $payslip,
            ['payslip_id' => $payslip->id, 'year' => $payslip->year, 'month' => $payslip->month]
        );

        return Inertia::render('Payslips/Show', [
            'payslip' => $payslip,
            'canManage' => $user->isHr() || $user->isAdmin(),
        ]);
    }

    /**
     * 新增薪資單 (限 HR / Admin)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user->isHr() && ! $user->isAdmin()) {
            abort(403, '僅人資或系統管理員可發布薪資單');
        }

        if ($request->filled('period') && (! $request->filled('year') || ! $request->filled('month'))) {
            $parts = explode('-', $request->period);
            if (count($parts) === 2) {
                $request->merge([
                    'year' => (int) $parts[0],
                    'month' => (int) $parts[1],
                ]);
            }
        }
        if ($request->filled('bonus') && ! $request->filled('performance_bonus')) {
            $request->merge(['performance_bonus' => $request->bonus]);
        }
        if ($request->filled('other_additions') && ! $request->filled('other_earnings')) {
            $request->merge(['other_earnings' => $request->other_additions]);
        }
        if ($request->filled('pension_self') && ! $request->filled('pension_contribution')) {
            $request->merge(['pension_contribution' => $request->pension_self]);
        }
        if ($request->filled('note') && ! $request->filled('notes')) {
            $request->merge(['notes' => $request->note]);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'performance_bonus' => ['nullable', 'numeric', 'min:0'],
            'overtime_pay' => ['nullable', 'numeric', 'min:0'],
            'other_earnings' => ['nullable', 'numeric', 'min:0'],
            'labor_insurance' => ['nullable', 'numeric', 'min:0'],
            'health_insurance' => ['nullable', 'numeric', 'min:0'],
            'pension_contribution' => ['nullable', 'numeric', 'min:0'],
            'tax_withholding' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published'],
            'notes' => ['nullable', 'string'],
        ]);

        // 避免重複
        $exists = Payslip::where('user_id', $validated['user_id'])
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => "該同仁在 {$validated['year']} 年 {$validated['month']} 月已有建立薪資單！"]);
        }

        $totals = Payslip::computeTotals($validated);

        $payslipData = array_merge($validated, $totals);
        if ($validated['status'] === 'published') {
            $payslipData['published_at'] = now();
        }

        $payslip = Payslip::create($payslipData);

        if ($payslip->status === 'published') {
            $targetUser = User::find($payslip->user_id);
            if ($targetUser) {
                $targetUser->notify(new EipSystemNotification(
                    title: '新薪資條送達通知',
                    message: "您 {$payslip->period_label} 的薪資明細已正式送達，點擊立即安全查閱",
                    type: 'payslip',
                    actionUrl: route('payslips.show', $payslip->id),
                    senderName: '人資財務部',
                    extra: ['payslip_id' => $payslip->id]
                ));
            }
        }

        AuditLog::log(
            'payslip_create',
            "建立 {$payslip->user->name} {$payslip->period_label} 薪資單",
            $payslip,
            ['payslip_id' => $payslip->id, 'net_pay' => $payslip->net_pay, 'status' => $payslip->status]
        );

        return back()->with('success', "{$payslip->period_label} 薪資單已成功建立！");
    }

    /**
     * 更新薪資單 (限 HR / Admin)
     */
    public function update(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        if (! $user->isHr() && ! $user->isAdmin()) {
            abort(403, '僅人資或系統管理員可維護薪資單');
        }

        $validated = $request->validate([
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'performance_bonus' => ['nullable', 'numeric', 'min:0'],
            'overtime_pay' => ['nullable', 'numeric', 'min:0'],
            'other_earnings' => ['nullable', 'numeric', 'min:0'],
            'labor_insurance' => ['nullable', 'numeric', 'min:0'],
            'health_insurance' => ['nullable', 'numeric', 'min:0'],
            'pension_contribution' => ['nullable', 'numeric', 'min:0'],
            'tax_withholding' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published'],
            'notes' => ['nullable', 'string'],
        ]);

        $totals = Payslip::computeTotals($validated);
        $updateData = array_merge($validated, $totals);

        $wasDraft = $payslip->status === 'draft';
        if ($wasDraft && $validated['status'] === 'published') {
            $updateData['published_at'] = now();
        }

        $payslip->update($updateData);

        if ($wasDraft && $validated['status'] === 'published') {
            $targetUser = $payslip->user;
            if ($targetUser) {
                $targetUser->notify(new EipSystemNotification(
                    title: '新薪資條送達通知',
                    message: "您 {$payslip->period_label} 的薪資明細已正式送達，點擊立即安全查閱",
                    type: 'payslip',
                    actionUrl: route('payslips.show', $payslip->id),
                    senderName: '人資財務部',
                    extra: ['payslip_id' => $payslip->id]
                ));
            }
        }

        AuditLog::log(
            'payslip_update',
            "更新 {$payslip->user->name} {$payslip->period_label} 薪資單",
            $payslip,
            ['payslip_id' => $payslip->id, 'net_pay' => $payslip->net_pay, 'status' => $payslip->status]
        );

        return back()->with('success', "薪資單已順利更新！");
    }

    /**
     * 刪除薪資單 (限 HR / Admin)
     */
    public function destroy(Payslip $payslip, Request $request)
    {
        $user = $request->user();
        if (! $user->isHr() && ! $user->isAdmin()) {
            abort(403, '僅人資或系統管理員可刪除薪資單');
        }

        $label = "{$payslip->user->name} {$payslip->period_label}";

        AuditLog::log(
            'payslip_delete',
            "刪除 {$label} 薪資單",
            $payslip,
            ['payslip_id' => $payslip->id]
        );

        $payslip->delete();

        return redirect()->route('payslips.index', ['view' => 'manage'])
            ->with('success', "{$label} 薪資單已成功刪除");
    }

    /**
     * 一鍵批次發布指定年月之所有草稿薪資單
     */
    public function publishBatch(Request $request)
    {
        $user = $request->user();
        if (! $user->isHr() && ! $user->isAdmin()) {
            abort(403, '僅人資或系統管理員可執行批次發布');
        }

        if ($request->filled('period') && (! $request->filled('year') || ! $request->filled('month'))) {
            $parts = explode('-', $request->period);
            if (count($parts) === 2) {
                $request->merge([
                    'year' => (int) $parts[0],
                    'month' => (int) $parts[1],
                ]);
            }
        }

        $validated = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $drafts = Payslip::where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->where('status', 'draft')
            ->get();

        if ($drafts->isEmpty()) {
            return back()->with('info', "{$validated['year']} 年 {$validated['month']} 月無待發布之草稿薪資單。");
        }

        $now = now();
        $count = $drafts->count();

        foreach ($drafts as $payslip) {
            $payslip->update([
                'status' => 'published',
                'published_at' => $now,
            ]);

            $target = $payslip->user;
            if ($target) {
                $target->notify(new EipSystemNotification(
                    title: '新薪資條送達通知',
                    message: "您 {$payslip->period_label} 的薪資明細已正式送達，點擊立即安全查閱",
                    type: 'payslip',
                    actionUrl: route('payslips.show', $payslip->id),
                    senderName: '人資財務部',
                    extra: ['payslip_id' => $payslip->id]
                ));
            }
        }

        AuditLog::log(
            'payslip_publish_batch',
            "一鍵批次發布 {$validated['year']} 年 {$validated['month']} 月全體薪資單 (共 {$count} 筆)",
            null,
            ['year' => $validated['year'], 'month' => $validated['month'], 'count' => $count]
        );

        return back()->with('success', "已成功發布 {$validated['year']} 年 {$validated['month']} 月共 {$count} 筆薪資單，並推播通知至同仁！");
    }
}
