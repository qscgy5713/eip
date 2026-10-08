<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrgManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $hrUser;
    protected User $manager;
    protected User $employee;
    protected Department $rdDept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $this->admin = User::where('role', 'admin')->first();
        $this->hrUser = User::where('role', 'hr')->first();
        $this->manager = User::where('role', 'manager')->first();
        $this->employee = User::where('role', 'employee')->first();
        $this->rdDept = Department::where('code', 'RD')->first();
    }

    public function test_guest_and_regular_employee_cannot_access_org_management(): void
    {
        // 訪客重定向
        $this->get(route('org-management.index'))->assertRedirect(route('login'));

        // 一般員工 403
        $this->actingAs($this->employee)
            ->get(route('org-management.index'))
            ->assertStatus(403);

        // 一般主管 403
        $this->actingAs($this->manager)
            ->get(route('org-management.index'))
            ->assertStatus(403);
    }

    public function test_admin_and_hr_can_access_org_management(): void
    {
        // 管理員存取
        $adminResponse = $this->actingAs($this->admin)->get(route('org-management.index'));
        $adminResponse->assertOk();
        $adminResponse->assertInertia(fn($page) => $page
            ->component('OrgManagement/Index')
            ->has('departments')
            ->has('users.data')
            ->has('stats')
        );

        // HR 存取
        $hrResponse = $this->actingAs($this->hrUser)->get(route('org-management.index'));
        $hrResponse->assertOk();
    }

    public function test_admin_can_create_department(): void
    {
        $response = $this->actingAs($this->admin)->post(route('org-management.departments.store'), [
            'name' => '客戶成功部',
            'code' => 'CS',
            'parent_id' => $this->rdDept->id,
            'leader_id' => $this->manager->id,
            'sort_order' => 50,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'name' => '客戶成功部',
            'code' => 'CS',
            'parent_id' => $this->rdDept->id,
            'leader_id' => $this->manager->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create_department',
        ]);
    }

    public function test_admin_can_update_department(): void
    {
        $response = $this->actingAs($this->admin)->put(route('org-management.departments.update', $this->rdDept->id), [
            'name' => '產品與研發總處',
            'code' => 'RD',
            'leader_id' => $this->manager->id,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->rdDept->refresh();
        $this->assertEquals('產品與研發總處', $this->rdDept->name);
        $this->assertEquals($this->manager->id, $this->rdDept->leader_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update_department',
        ]);
    }

    public function test_cannot_delete_department_with_users_or_children(): void
    {
        // 研發部內有同仁，嘗試刪除應被阻擋
        $responseWithUsers = $this->actingAs($this->admin)
            ->delete(route('org-management.departments.destroy', $this->rdDept->id));

        $responseWithUsers->assertRedirect();
        $responseWithUsers->assertSessionHasErrors('error');
        $this->assertDatabaseHas('departments', ['id' => $this->rdDept->id]);

        // 建立子部門測試防呆
        $childDept = Department::create([
            'name' => '前端組',
            'code' => 'FE',
            'parent_id' => $this->rdDept->id,
        ]);

        // 將研發部同仁移出
        User::where('department_id', $this->rdDept->id)->update(['department_id' => null]);

        // 雖然無同仁但有子部門，仍應阻擋刪除
        $responseWithChild = $this->actingAs($this->admin)
            ->delete(route('org-management.departments.destroy', $this->rdDept->id));

        $responseWithChild->assertRedirect();
        $responseWithChild->assertSessionHasErrors('error');
        $this->assertDatabaseHas('departments', ['id' => $this->rdDept->id]);
    }

    public function test_admin_can_delete_empty_department(): void
    {
        $emptyDept = Department::create([
            'name' => '臨時專案組',
            'code' => 'TMP',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('org-management.departments.destroy', $emptyDept->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('departments', ['id' => $emptyDept->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'delete_department',
        ]);
    }

    public function test_admin_can_create_user_and_auto_initializes_leave_balances(): void
    {
        $response = $this->actingAs($this->admin)->post(route('org-management.users.store'), [
            'name' => '新進工程師',
            'email' => 'newbie@example.com',
            'employee_no' => 'EMP-999',
            'department_id' => $this->rdDept->id,
            'job_title' => '後端工程師',
            'role' => 'employee',
            'phone' => '0912-345-678',
            'password' => 'secret1234',
        ]);

        $response->assertRedirect();
        $newUser = User::where('email', 'newbie@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue(Hash::check('secret1234', $newUser->password));
        $this->assertEquals('EMP-999', $newUser->employee_no);
        $this->assertEquals('active', $newUser->status);

        // 驗證是否自動初始化當年度休假額度 (特休等)
        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $newUser->id,
            'leave_type' => 'annual',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create_user',
        ]);
    }

    public function test_admin_can_update_user_info(): void
    {
        $response = $this->actingAs($this->admin)->put(route('org-management.users.update', $this->employee->id), [
            'name' => '資深研發同仁',
            'email' => $this->employee->email,
            'employee_no' => 'EMP-888',
            'department_id' => $this->rdDept->id,
            'job_title' => '資深架構師',
            'role' => 'employee',
            'phone' => '0988-777-666',
        ]);

        $response->assertRedirect();
        $this->employee->refresh();
        $this->assertEquals('資深研發同仁', $this->employee->name);
        $this->assertEquals('EMP-888', $this->employee->employee_no);
        $this->assertEquals('資深架構師', $this->employee->job_title);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update_user',
        ]);
    }

    public function test_admin_can_reset_user_password(): void
    {
        $response = $this->actingAs($this->admin)->post(route('org-management.users.reset-password', $this->employee->id), [
            'password' => 'newPassword2026',
            'password_confirmation' => 'newPassword2026',
        ]);

        $response->assertRedirect();
        $this->employee->refresh();
        $this->assertTrue(Hash::check('newPassword2026', $this->employee->password));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reset_user_password',
        ]);
    }

    public function test_change_user_status_and_suspended_user_cannot_login(): void
    {
        // 1. 將員工設為停權 suspended
        $statusResponse = $this->actingAs($this->admin)->post(route('org-management.users.status', $this->employee->id), [
            'status' => 'suspended',
        ]);

        $statusResponse->assertRedirect();
        $this->employee->refresh();
        $this->assertEquals('suspended', $this->employee->status);

        // 登出管理員身分
        $this->post('/logout');

        // 2. 嘗試使用停權帳號登入系統
        $loginResponse = $this->post(route('login'), [
            'email' => $this->employee->email,
            'password' => 'password',
        ]);

        // 登入失敗並拋出錯誤訊息
        $loginResponse->assertSessionHasErrors('email');
        $this->assertGuest();

        // 3. 管理員重新登入並復原員工為在職 active
        $this->actingAs($this->admin)->post(route('org-management.users.status', $this->employee->id), [
            'status' => 'active',
        ]);

        // 登出管理員身分
        $this->post('/logout');

        // 4. 再次嘗試登入成功
        $reLoginResponse = $this->post(route('login'), [
            'email' => $this->employee->email,
            'password' => 'password',
        ]);

        $reLoginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->employee);
    }

    public function test_admin_and_hr_can_move_department_via_org_chart(): void
    {
        $mktDept = Department::where('code', 'MKT')->first();
        $this->assertNotNull($mktDept);

        // 1. 建立一個新子部門，原本隸屬於 RD
        $subDept = Department::create([
            'name' => '雲端架構組',
            'code' => 'RD_CLOUD',
            'parent_id' => $this->rdDept->id,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        // 2. 拖曳將子部門調整隸屬於行銷業務部 MKT
        $response = $this->actingAs($this->admin)->patch(route('org-management.departments.move', $subDept->id), [
            'parent_id' => $mktDept->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $subDept->refresh();
        $this->assertEquals($mktDept->id, $subDept->parent_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'move_department',
        ]);

        // 3. 拖曳將子部門提升為頂層公司直屬部門 (parent_id = null)
        $rootResponse = $this->actingAs($this->hrUser)->patch(route('org-management.departments.move', $subDept->id), [
            'parent_id' => null,
        ]);

        $rootResponse->assertRedirect();
        $subDept->refresh();
        $this->assertNull($subDept->parent_id);
    }

    public function test_cannot_move_department_to_itself(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('org-management.departments.move', $this->rdDept->id), [
            'parent_id' => $this->rdDept->id,
        ]);

        $response->assertSessionHasErrors(['parent_id']);
    }

    public function test_cannot_move_department_to_its_descendant_preventing_cycle(): void
    {
        // 建立階層：RD -> 子部門 Frontend -> 孫部門 UI
        $frontendDept = Department::create([
            'name' => '前端小組',
            'code' => 'RD_FE',
            'parent_id' => $this->rdDept->id,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $uiDept = Department::create([
            'name' => 'UI 設計分組',
            'code' => 'RD_FE_UI',
            'parent_id' => $frontendDept->id,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        // 嘗試將頂層 RD 部門拖曳移到其孫部門 UI 設計分組底下 -> 應被循環依賴阻擋
        $response = $this->actingAs($this->admin)->patch(route('org-management.departments.move', $this->rdDept->id), [
            'parent_id' => $uiDept->id,
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->rdDept->refresh();
        // 原 parent_id 不應被變更
        $this->assertNotEquals($uiDept->id, $this->rdDept->parent_id);
    }

    public function test_regular_employee_cannot_move_department(): void
    {
        $response = $this->actingAs($this->employee)->patch(route('org-management.departments.move', $this->rdDept->id), [
            'parent_id' => null,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_and_hr_can_add_existing_user_to_department_via_dropdown(): void
    {
        // 建立一位尚未分配部門的同仁 (例如公司剛建立帳號的同仁)
        $unassignedUser = User::factory()->create([
            'department_id' => null,
            'status' => 'active',
            'role' => 'employee',
        ]);

        // 1. 管理員將該同仁指派至 RD 部門
        $response = $this->actingAs($this->admin)->post(route('org-management.departments.members.add', $this->rdDept->id), [
            'user_id' => $unassignedUser->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $unassignedUser->refresh();
        $this->assertEquals($this->rdDept->id, $unassignedUser->department_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assign_department_member',
        ]);

        // 2. HR 將該同仁調任至 HR 部門
        $hrDept = Department::where('code', 'HR')->first();
        $transferResponse = $this->actingAs($this->hrUser)->post(route('org-management.departments.members.add', $hrDept->id), [
            'user_id' => $unassignedUser->id,
        ]);

        $transferResponse->assertRedirect();
        $unassignedUser->refresh();
        $this->assertEquals($hrDept->id, $unassignedUser->department_id);
    }

    public function test_admin_and_hr_can_remove_member_from_department(): void
    {
        $this->assertEquals($this->rdDept->id, $this->employee->department_id);

        // 移出同仁
        $response = $this->actingAs($this->admin)->delete(route('org-management.departments.members.remove', [
            $this->rdDept->id,
            $this->employee->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->employee->refresh();
        $this->assertNull($this->employee->department_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'remove_department_member',
        ]);
    }

    public function test_removing_department_leader_clears_leader_id(): void
    {
        // 將 manager 設為 rdDept 主管
        $this->rdDept->update(['leader_id' => $this->manager->id]);
        $this->manager->update(['department_id' => $this->rdDept->id]);

        $response = $this->actingAs($this->admin)->delete(route('org-management.departments.members.remove', [
            $this->rdDept->id,
            $this->manager->id,
        ]));

        $response->assertRedirect();
        $this->rdDept->refresh();
        $this->manager->refresh();

        $this->assertNull($this->manager->department_id);
        $this->assertNull($this->rdDept->leader_id);
    }

    public function test_regular_employee_cannot_add_or_remove_department_members(): void
    {
        $addResponse = $this->actingAs($this->employee)->post(route('org-management.departments.members.add', $this->rdDept->id), [
            'user_id' => $this->employee->id,
        ]);
        $addResponse->assertStatus(403);

        $removeResponse = $this->actingAs($this->employee)->delete(route('org-management.departments.members.remove', [
            $this->rdDept->id,
            $this->employee->id,
        ]));
        $removeResponse->assertStatus(403);
    }

    public function test_admin_and_hr_can_set_and_clear_department_leader(): void
    {
        // 1. 指派 employee 為主管
        $response = $this->actingAs($this->admin)->post(route('org-management.departments.leader', $this->rdDept->id), [
            'leader_id' => $this->employee->id,
        ]);

        $response->assertRedirect();
        $this->rdDept->refresh();
        $this->employee->refresh();

        $this->assertEquals($this->employee->id, $this->rdDept->leader_id);
        $this->assertEquals($this->rdDept->id, $this->employee->department_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'set_department_leader',
        ]);

        // 2. 清除主管職務
        $clearResponse = $this->actingAs($this->admin)->post(route('org-management.departments.leader', $this->rdDept->id), [
            'leader_id' => null,
        ]);

        $clearResponse->assertRedirect();
        $this->rdDept->refresh();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'set_department_leader',
        ]);

        // 3. 一般員工無法設置主管
        $unauthResponse = $this->actingAs($this->employee)->post(route('org-management.departments.leader', $this->rdDept->id), [
            'leader_id' => $this->employee->id,
        ]);
        $unauthResponse->assertStatus(403);
    }

    public function test_admin_and_hr_can_export_roster_csv(): void
    {
        // 一般員工禁止匯出
        $this->actingAs($this->employee)
            ->get(route('org-management.export-roster'))
            ->assertStatus(403);

        // 管理員成功匯出
        $response = $this->actingAs($this->admin)->get(route('org-management.export-roster'));

        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), 'attachment'));

        $content = $response->streamedContent();
        // 包含 UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        // 包含部門代碼與表頭
        $this->assertStringContainsString('部門代碼', $content);
        $this->assertStringContainsString('同仁姓名', $content);
        $this->assertStringContainsString($this->rdDept->name, $content);

        // 審計留痕
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'export_organization_roster',
        ]);
    }
}
