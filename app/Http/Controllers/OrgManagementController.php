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

        // 1. 部門清單 (包含父部門、主管、所屬同仁與人數統計)
        $departments = Department::with([
            'parent:id,name',
            'leader:id,name,email,job_title',
            'users' => function ($q) {
                $q->select('id', 'name', 'email', 'employee_no', 'job_title', 'role', 'status', 'phone', 'department_id')
                    ->orderBy('name');
            },
        ])
            ->withCount('users')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // 記憶體快速計算各部門總編制人數 (包含自身直接同仁與其所有子孫部門之同仁)
        $directCounts = $departments->pluck('users_count', 'id')->toArray();
        foreach ($departments as $dept) {
            $headcount = $directCounts[$dept->id] ?? 0;
            foreach ($dept->getAllDescendantIds() as $descendantId) {
                $headcount += $directCounts[$descendantId] ?? 0;
            }
            $dept->total_headcount = $headcount;
        }

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
        $activeUsers = User::with('department:id,name,code')
            ->where('status', 'active')
            ->select('id', 'name', 'email', 'employee_no', 'job_title', 'department_id', 'role')
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

        if (!empty($validated['parent_id'])) {
            $descendantIds = $department->getAllDescendantIds();
            if (in_array((int)$validated['parent_id'], $descendantIds)) {
                return redirect()->back()->withErrors([
                    'parent_id' => '不可將部門設定隸屬於其子部門或下層後代部門，避免形成循環階層引用。',
                ]);
            }
        }

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
     * 拖曳調整組織部門階層隸屬 (Drag & Drop Re-parent)
     */
    public function moveDepartment(Request $request, Department $department): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403, '僅限系統管理員或人資管理員可調整組織架構。');
        }

        $validated = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
                Rule::notIn([$department->id]),
            ],
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'parent_id.not_in' => '不可將部門拖曳設置隸屬於自己。',
        ]);

        $newParentId = $validated['parent_id'] ? (int) $validated['parent_id'] : null;

        // 循環依賴檢測
        if ($newParentId !== null) {
            $descendantIds = $department->getAllDescendantIds();
            if (in_array($newParentId, $descendantIds)) {
                return redirect()->back()->withErrors([
                    'error' => "無法將部門「{$department->name}」移動至其下層子部門底下，避免組織形成循環階層。",
                ]);
            }
        }

        $oldParent = $department->parent?->name ?? '頂層公司';
        $oldParentId = $department->parent_id;

        $department->parent_id = $newParentId;
        if (isset($validated['sort_order'])) {
            $department->sort_order = $validated['sort_order'];
        }
        $department->save();

        $newParentName = $newParentId ? Department::find($newParentId)?->name : '頂層公司';

        AuditLog::log(
            action: 'move_department',
            description: "管理者 {$operator->name} 透過視覺組織圖調整了部門「{$department->name}」的組織隸屬關係（從「{$oldParent}」調整至「{$newParentName}」）",
            auditable: $department,
            details: [
                'department_id' => $department->id,
                'old_parent_id' => $oldParentId,
                'new_parent_id' => $newParentId,
            ]
        );

        return redirect()->back()->with('success', "已成功將部門「{$department->name}」調整隸屬於「{$newParentName}」！");
    }

    /**
     * 快速指派或解除部門負責主管
     */
    public function setLeader(Request $request, Department $department): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403, '僅限系統管理員或人資管理員可指派部門主管。');
        }

        $validated = $request->validate([
            'leader_id' => 'nullable|integer|exists:users,id',
        ]);

        $newLeaderId = $validated['leader_id'] ?? null;
        $leaderUser = $newLeaderId ? User::find($newLeaderId) : null;

        // 若指派主管，確認該同仁隸屬於此部門 (若原本未分配或在他部，自動調任至此部門)
        if ($leaderUser && $leaderUser->department_id !== $department->id) {
            $leaderUser->update(['department_id' => $department->id]);
        }

        $oldLeaderName = $department->leader?->name ?? '未指定';
        $department->update(['leader_id' => $newLeaderId]);

        AuditLog::log(
            action: 'set_department_leader',
            description: $leaderUser
                ? "管理者 {$operator->name} 將部門「{$department->name}」的主管設定為「{$leaderUser->name}」（原主管：{$oldLeaderName}）"
                : "管理者 {$operator->name} 解除了部門「{$department->name}」的主管職務（原主管：{$oldLeaderName}）",
            auditable: $department,
            details: [
                'department_id' => $department->id,
                'old_leader_name' => $oldLeaderName,
                'new_leader_id' => $newLeaderId,
            ]
        );

        return redirect()->back()->with('success', $leaderUser
            ? "已成功將「{$leaderUser->name}」指派為「{$department->name}」的主管！"
            : "已成功解除「{$department->name}」的主管職務！"
        );
    }

    /**
     * 匯出企業組織架構與人員編制表 (UTF-8 BOM CSV)
     */
    public function exportRoster(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403, '僅限系統管理員或人資管理員可匯出組織編制表。');
        }

        $departments = Department::with([
            'parent:id,name,code',
            'leader:id,name,job_title',
            'users' => function ($q) {
                $q->orderBy('name');
            },
        ])
            ->withCount('users')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $directCounts = $departments->pluck('users_count', 'id')->toArray();
        foreach ($departments as $dept) {
            $headcount = $directCounts[$dept->id] ?? 0;
            foreach ($dept->getAllDescendantIds() as $descendantId) {
                $headcount += $directCounts[$descendantId] ?? 0;
            }
            $dept->total_headcount = $headcount;
        }

        AuditLog::log(
            action: 'export_organization_roster',
            description: "管理者 {$operator->name} 匯出了全公司組織架構與人員編制表 CSV",
            details: [
                'total_departments' => $departments->count(),
            ]
        );

        $fileName = '組織架構與人員編制表_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($departments) {
            $handle = fopen('php://output', 'w');
            // 寫入 UTF-8 BOM 避免 Excel 亂碼
            fputs($handle, "\xEF\xBB\xBF");

            // 標題行
            fputcsv($handle, [
                '部門代碼',
                '部門名稱',
                '上級部門',
                '部門負責人(主管)',
                '部門直屬在職人數',
                '轄下總編制(含子部門)',
                '同仁工號',
                '同仁姓名',
                '帳號(Email)',
                '職稱',
                '系統角色',
                '在職狀態',
                '聯絡電話',
            ]);

            foreach ($departments as $dept) {
                $parentName = $dept->parent?->name ?? '頂層公司';
                $leaderName = $dept->leader?->name ? "{$dept->leader->name} ({$dept->leader->job_title})" : '未指定';

                if ($dept->users->isEmpty()) {
                    fputcsv($handle, [
                        $dept->code,
                        $dept->name,
                        $parentName,
                        $leaderName,
                        0,
                        $dept->total_headcount,
                        '-',
                        '(無在職同仁)',
                        '-',
                        '-',
                        '-',
                        '-',
                        '-',
                    ]);
                } else {
                    foreach ($dept->users as $u) {
                        $roleMap = [
                            'admin' => '系統管理員',
                            'manager' => '部門主管',
                            'hr' => '人資主管',
                            'employee' => '一般同仁',
                        ];
                        $statusMap = [
                            'active' => '在職正常',
                            'suspended' => '暫時停權',
                            'resigned' => '已離職',
                        ];

                        fputcsv($handle, [
                            $dept->code,
                            $dept->name,
                            $parentName,
                            $leaderName,
                            $dept->users_count,
                            $dept->total_headcount,
                            $u->employee_no ?? '-',
                            $u->name . ($u->id === $dept->leader_id ? ' ★(主管)' : ''),
                            $u->email,
                            $u->job_title ?? '未設職稱',
                            $roleMap[$u->role] ?? $u->role,
                            $statusMap[$u->status] ?? $u->status,
                            $u->phone ?? '-',
                        ]);
                    }
                }
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * 加入現有同仁至部門 (從全公司在職同仁名冊下拉選單指派)
     */
    public function addMember(Request $request, Department $department): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403, '僅限系統管理員或人資管理員可調整部門成員。');
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::with('department')->findOrFail($validated['user_id']);

        if ($user->department_id === $department->id) {
            return redirect()->back()->with('info', "同仁「{$user->name}」已在「{$department->name}」中。");
        }

        $previousDept = $user->department;
        $user->update(['department_id' => $department->id]);

        AuditLog::log(
            action: 'assign_department_member',
            description: $previousDept
                ? "管理者 {$operator->name} 將同仁「{$user->name}」從「{$previousDept->name}」調任至「{$department->name}」"
                : "管理者 {$operator->name} 將同仁「{$user->name}」指派加入部門「{$department->name}」",
            auditable: $department,
            details: [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'from_department_id' => $previousDept?->id,
                'to_department_id' => $department->id,
            ]
        );

        return redirect()->back()->with('success', "已成功將同仁「{$user->name}」加入部門「{$department->name}」！");
    }

    /**
     * 從部門移出同仁 (移出後同仁仍保留帳號，部門設為未分配)
     */
    public function removeMember(Request $request, Department $department, User $user): RedirectResponse
    {
        $operator = $request->user();
        if (!$operator->isAdmin() && !$operator->isHr()) {
            abort(403, '僅限系統管理員或人資管理員可調整部門成員。');
        }

        if ($user->department_id !== $department->id) {
            return redirect()->back()->withErrors([
                'error' => "同仁「{$user->name}」目前不屬於部門「{$department->name}」。",
            ]);
        }

        // 若該同仁恰好為該部門主管，同步清空主管設定
        if ($department->leader_id === $user->id) {
            $department->update(['leader_id' => null]);
        }

        $user->update(['department_id' => null]);

        AuditLog::log(
            action: 'remove_department_member',
            description: "管理者 {$operator->name} 將同仁「{$user->name}」從部門「{$department->name}」移出",
            auditable: $department,
            details: [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'department_id' => $department->id,
            ]
        );

        return redirect()->back()->with('success', "已將同仁「{$user->name}」從部門「{$department->name}」移出！");
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
