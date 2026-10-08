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
}
