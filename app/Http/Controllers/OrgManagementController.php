<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class OrgManagementController extends Controller
{
    public function __construct(
        protected LeaveBalanceService $leaveBalanceService
    ) {}

    /**
     * 組織與同仁管理主頁
     */
    public function index(Request $request): Response
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin() && !$currentUser->isHr()) {
            abort(403, '僅限系統管理員或人資管理員存取組織管理中心。');
        }

        // 1. 部門清單 (包含父部門、主管與人數統計)
        $departments = Department::with([
            'parent:id,name',
            'leader:id,name,email,job_title',
        ])
            ->withCount('users')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // 2. 同仁列表篩選查詢
        $userQuery = User::with('department:id,name,code')
            ->select([
                'id',
                'name',
                'email',
                'employee_no',
                'department_id',
                'job_title',
                'role',
                'phone',
                'status',
                'created_at',
            ]);

        if ($request->filled('department_id')) {
            $userQuery->where('department_id', $request->input('department_id'));
        }

        if ($request->filled('role')) {
            $userQuery->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $userQuery->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('employee_no', 'ilike', "%{$search}%")
                    ->orWhere('job_title', 'ilike', "%{$search}%");
            });
        }

        $users = $userQuery->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // 3. 所有在職候選主管/人員名單 (供下拉選單快速指派)
        $activeUsers = User::where('status', 'active')
            ->select('id', 'name', 'job_title', 'department_id')
            ->orderBy('name')
            ->get();

        // 4. 關鍵指標統計
        $stats = [
            'total_departments' => Department::count(),
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'suspended_or_resigned' => User::whereIn('status', ['suspended', 'resigned'])->count(),
        ];

        return Inertia::render('OrgManagement/Index', [
            'departments' => $departments,
            'users' => $users,
            'activeUsers' => $activeUsers,
            'stats' => $stats,
            'filters' => [
                'department_id' => $request->input('department_id', ''),
                'role' => $request->input('role', ''),
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
                'tab' => $request->input('tab', 'departments'), // 'departments' or 'users'
            ],
        ]);
    }

    /**
     * 新增部門
     */
    public function storeDepartment(Request $request): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:20|unique:departments,code',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'leader_id' => 'nullable|integer|exists:users,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $department = Department::create($validated);

        AuditLog::log(
            action: 'create_department',
            description: "管理者 {$operator->name} 建立了新部門「{$department->name}」({$department->code})",
            auditable: $department,
            details: $validated
        );

        return redirect()->back()->with('success', "部門「{$department->name}」已順利建立！");
    }

    /**
     * 更新部門
     */
    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department->id)],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
                Rule::notIn([$department->id]), // 防範將自己設為父部門
            ],
            'leader_id' => 'nullable|integer|exists:users,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? $department->sort_order;
        $department->update($validated);

        AuditLog::log(
            action: 'update_department',
            description: "管理者 {$operator->name} 更新了部門「{$department->name}」的組織資訊",
            auditable: $department,
            details: $validated
        );

        return redirect()->back()->with('success', "部門「{$department->name}」資訊已成功儲存！");
    }

    /**
     * 刪除部門 (具備嚴格防呆防刪保護)
     */
    public function destroyDepartment(Request $request, Department $department): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin()) {
            abort(403, '僅限系統管理員可刪除部門。');
        }

        // 防呆 1：部門內仍有關聯員工
        $userCount = $department->users()->count();
        if ($userCount > 0) {
            return redirect()->back()->withErrors([
                'error' => "無法刪除部門「{$department->name}」，因部門內尚有 {$userCount} 名在職員工。請先將員工轉移至其他部門。",
            ]);
        }

        // 防呆 2：部門下轄有子部門
        $childrenCount = $department->children()->count();
        if ($childrenCount > 0) {
            return redirect()->back()->withErrors([
                'error' => "無法刪除部門「{$department->name}」，因該部門下轄有 {$childrenCount} 個子部門。請先重新調整子部門之所屬關係。",
            ]);
        }

        $deptName = $department->name;
        $deptCode = $department->code;
        $department->delete();

        AuditLog::log(
            action: 'delete_department',
            description: "系統管理員 {$operator->name} 刪除了部門「{$deptName}」({$deptCode})",
            details: ['deleted_name' => $deptName, 'deleted_code' => $deptCode]
        );

        return redirect()->back()->with('success', "部門「{$deptName}」已安全移除。");
    }

    /**
     * 新增員工帳號
     */
    public function storeUser(Request $request): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|string|email|max:100|unique:users,email',
            'employee_no' => 'nullable|string|max:30|unique:users,employee_no',
            'department_id' => 'nullable|integer|exists:departments,id',
            'job_title' => 'nullable|string|max:50',
            'role' => 'required|string|in:admin,manager,employee,hr',
            'phone' => 'nullable|string|max:30',
            'password' => ['required', 'string', 'min:8'],
        ]);

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'employee_no' => $validated['employee_no'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        // 為新同仁初始化當年度法定休假額度模板
        $this->leaveBalanceService->initUserBalances($newUser, (int) date('Y'));

        AuditLog::log(
            action: 'create_user',
            description: "管理者 {$operator->name} 建立了新同仁帳號「{$newUser->name}」({$newUser->email} / 角色: {$newUser->role})",
            auditable: $newUser,
            details: [
                'id' => $newUser->id,
                'name' => $newUser->name,
                'email' => $newUser->email,
                'role' => $newUser->role,
                'department_id' => $newUser->department_id,
            ]
        );

        return redirect()->back()->with('success', "同仁「{$newUser->name}」帳號已成功建立，並已自動配置年度休假額度！");
    }

    /**
     * 更新員工資訊
     */
    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403);
        }

        // 非 admin 不可提拔他人為 admin 或修改 admin 資料
        if (!$operator->isAdmin() && ($user->isAdmin() || $request->input('role') === 'admin')) {
            abort(403, '僅系統管理員有權調派或指派管理員身分。');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'email' => ['required', 'string', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_no' => ['nullable', 'string', 'max:30', Rule::unique('users', 'employee_no')->ignore($user->id)],
            'department_id' => 'nullable|integer|exists:departments,id',
            'job_title' => 'nullable|string|max:50',
            'role' => 'required|string|in:admin,manager,employee,hr',
            'phone' => 'nullable|string|max:30',
        ]);

        // 防呆：若該人員為最後一名系統管理員，禁止將其角色降級
        if ($user->isAdmin() && $validated['role'] !== 'admin') {
            $otherAdminCount = User::where('role', 'admin')->where('id', '!=', $user->id)->where('status', 'active')->count();
            if ($otherAdminCount === 0) {
                return redirect()->back()->withErrors([
                    'error' => '無法降級該同仁身分，系統必須至少保留一名啟用中的系統管理員 (Admin)。',
                ]);
            }
        }

        $user->update($validated);

        AuditLog::log(
            action: 'update_user',
            description: "管理者 {$operator->name} 更新了同仁「{$user->name}」的人事資料",
            auditable: $user,
            details: $validated
        );

        return redirect()->back()->with('success', "同仁「{$user->name}」的人事資料已順利更新！");
    }

    /**
     * 重設同仁登入密碼
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403);
        }

        if (!$operator->isAdmin() && $user->isAdmin()) {
            abort(403, '僅系統管理員有權重設其他管理員之密碼。');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::log(
            action: 'reset_user_password',
            description: "管理者 {$operator->name} 重設了同仁「{$user->name}」的登入密碼",
            auditable: $user
        );

        return redirect()->back()->with('success', "同仁「{$user->name}」的密碼已成功重設！");
    }

    /**
     * 變更同仁在職狀態 (在職 / 停權 / 離職)
     */
    public function updateUserStatus(Request $request, User $user): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403);
        }

        // 防呆：不得變更自己本人的狀態
        if ($operator->id === $user->id) {
            return redirect()->back()->withErrors([
                'error' => '無法變更您自身的在職狀態。',
            ]);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:active,suspended,resigned',
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $user->status;

        $user->update(['status' => $newStatus]);

        $statusLabels = [
            'active' => '在職正常',
            'suspended' => '暫時停權',
            'resigned' => '已離職',
        ];

        AuditLog::log(
            action: 'change_user_status',
            description: "管理者 {$operator->name} 將同仁「{$user->name}」之在職狀態由「{$statusLabels[$oldStatus]}」變更為「{$statusLabels[$newStatus]}」",
            auditable: $user,
            details: [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]
        );

        return redirect()->back()->with('success', "同仁「{$user->name}」的在職狀態已更新為【{$statusLabels[$newStatus]}】！");
    }
}
