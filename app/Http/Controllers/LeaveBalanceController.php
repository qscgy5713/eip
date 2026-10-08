<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveBalanceController extends Controller
{
    public function __construct(
        protected LeaveBalanceService $leaveBalanceService
    ) {}

    /**
     * 休假額度總覽看板（個人額度卡片 + HR/管理員同仁配額管理）
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $selectedYear = (int) $request->input('year', date('Y'));
        $canManage = $user->isHr() || $user->isAdmin();

        // 1. 個人休假額度卡片清單
        $myBalances = $this->leaveBalanceService->getUserBalances($user, $selectedYear);

        // 2. HR / 管理員專屬：同仁額度管轄清單
        $managedUsers = null;
        $departments = [];

        if ($canManage) {
            $departments = Department::select('id', 'name')->get();

            $query = User::with(['department', 'leaveBalances' => function ($q) use ($selectedYear) {
                $q->where('year', $selectedYear);
            }]);

            if ($request->filled('department_id')) {
                $query->where('department_id', $request->input('department_id'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('employee_no', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            }

            $managedUsers = $query->orderBy('name')->paginate(12)->withQueryString();

            // 為尚未生成當年度記錄的同仁補齊預設值物件，避免前端顯示空陣列
            $managedUsers->getCollection()->transform(function ($targetUser) use ($selectedYear) {
                if ($targetUser->leaveBalances->isEmpty()) {
                    $this->leaveBalanceService->initUserBalances($targetUser, $selectedYear);
                    $targetUser->load(['leaveBalances' => fn($q) => $q->where('year', $selectedYear)]);
                }
                return $targetUser;
            });
        }

        // 3. 個人請假申請與折抵歷史紀錄 (Leave History Ledger)
        $leaveForm = \App\Models\Form::where('code', 'LEAVE')->first();
        $myLeaveRequests = [];
        if ($leaveForm) {
            $myLeaveRequests = \App\Models\FormRequest::with(['form:id,name,code'])
                ->where('user_id', $user->id)
                ->where('form_id', $leaveForm->id)
                ->latest()
                ->take(15)
                ->get()
                ->map(function ($req) {
                    $fd = $req->data ?? [];
                    return [
                        'id' => $req->id,
                        'title' => $req->title,
                        'leave_type' => $fd['leave_type'] ?? '特休假',
                        'start_date' => $fd['start_date'] ?? '',
                        'end_date' => $fd['end_date'] ?? '',
                        'days' => (float) ($fd['days'] ?? 0),
                        'reason' => $fd['reason'] ?? '',
                        'status' => $req->status,
                        'created_at' => $req->created_at->format('Y-m-d H:i'),
                    ];
                });
        }

        // 4. 個人加班申請與補休折算入帳明細 (Overtime & Compensatory Credits Ledger)
        $overtimeForm = \App\Models\Form::where('code', 'OVERTIME')->first();
        $myOvertimeRequests = [];
        if ($overtimeForm) {
            $myOvertimeRequests = \App\Models\FormRequest::with(['form:id,name,code'])
                ->where('user_id', $user->id)
                ->where('form_id', $overtimeForm->id)
                ->latest()
                ->take(15)
                ->get()
                ->map(function ($req) {
                    $fd = $req->data ?? [];
                    $hours = (float) ($fd['hours'] ?? 0);
                    $comp = $fd['compensation'] ?? '換取補休時數';
                    $isComp = str_contains($comp, '補休');
                    $creditDays = $isComp ? round($hours / 8.0, 2) : 0;
                    return [
                        'id' => $req->id,
                        'title' => $req->title,
                        'overtime_date' => $fd['overtime_date'] ?? '',
                        'overtime_type' => $fd['overtime_type'] ?? '平日延長工時',
                        'hours' => $hours,
                        'compensation' => $comp,
                        'credit_days' => $creditDays,
                        'reason' => $fd['reason'] ?? '',
                        'status' => $req->status,
                        'created_at' => $req->created_at->format('Y-m-d H:i'),
                    ];
                });
        }

        return Inertia::render('LeaveBalances/Index', [
            'myBalances' => $myBalances,
            'myLeaveRequests' => $myLeaveRequests,
            'myOvertimeRequests' => $myOvertimeRequests,
            'leaveFormId' => $leaveForm?->id,
            'overtimeFormId' => $overtimeForm?->id,
            'selectedYear' => $selectedYear,
            'canManage' => $canManage,
            'managedUsers' => $managedUsers,
            'departments' => $departments,
            'leaveTypesMeta' => LeaveBalance::LEAVE_TYPES,
            'filters' => [
                'department_id' => $request->input('department_id', ''),
                'search' => $request->input('search', ''),
                'year' => $selectedYear,
            ],
        ]);
    }

    /**
     * HR / 管理員調整指定同仁之假別配額
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isHr() && !$operator->isAdmin()) {
            abort(403, '僅人資或系統管理員具備調整休假額度權限。');
        }

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'leave_type' => 'required|string|max:30',
            'allocated_days' => 'required|numeric|min:0|max:365',
            'note' => 'nullable|string|max:255',
        ]);

        $balance = $this->leaveBalanceService->updateQuota(
            targetUser: $user,
            year: (int) $validated['year'],
            rawType: $validated['leave_type'],
            allocatedDays: (float) $validated['allocated_days'],
            note: $validated['note'] ?? null,
            operator: $operator
        );

        return redirect()->back()->with('success', "已成功更新同仁 {$user->name} 的「{$balance->type_label}」配額（{$balance->allocated_days} 天）！");
    }

    /**
     * HR / 管理員批次初始化年度休假配額
     */
    public function batchInit(Request $request): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isHr() && !$operator->isAdmin()) {
            abort(403, '僅人資或系統管理員具備批次初始化休假額度權限。');
        }

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $count = $this->leaveBalanceService->batchInitYear(
            year: (int) $validated['year'],
            departmentId: !empty($validated['department_id']) ? (int) $validated['department_id'] : null,
            operator: $operator
        );

        return redirect()->back()->with('success', "已成功為 {$count} 位同仁初始化 {$validated['year']} 年度各類假別法定配額！");
    }
}
