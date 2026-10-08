<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use App\Models\Delegation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $employee;
    protected Department $department;
    protected Form $leaveForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '研發部',
            'code' => 'RD',
        ]);

        $this->admin = User::factory()->create([
            'department_id' => $this->department->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'department_id' => $this->department->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->employee = User::factory()->create([
            'department_id' => $this->department->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->department->update(['manager_id' => $this->manager->id]);

        $this->leaveForm = Form::firstOrCreate(
            ['code' => 'LEAVE'],
            [
                'name' => '請假申請單',
                'category' => 'hr',
                'description' => '請假單',
                'schema' => [
                    ['name' => 'leave_type', 'type' => 'select', 'label' => '假別'],
                    ['name' => 'start_date', 'type' => 'date', 'label' => '開始日期'],
                    ['name' => 'end_date', 'type' => 'date', 'label' => '結束日期'],
                    ['name' => 'days', 'type' => 'number', 'label' => '天數'],
                ],
                'is_active' => true,
            ]
        );
    }

    public function test_regular_employee_dashboard_view(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('myLeaveSummary')
            ->where('teamAttendanceSnapshot', null)
            ->has('stats')
        );
    }

    public function test_manager_dashboard_includes_team_attendance_snapshot(): void
    {
        // 員工請假已核准
        $today = now()->toDateString();
        FormRequest::create([
            'request_no' => 'REQ-LEAVE-001',
            'form_id' => $this->leaveForm->id,
            'user_id' => $this->employee->id,
            'title' => '員工請特休',
            'data' => [
                'leave_type' => 'annual',
                'start_date' => $today,
                'end_date' => $today,
                'days' => 1,
            ],
            'status' => 'approved',
            'current_stage' => 1,
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('teamAttendanceSnapshot.total_members', 3)
            ->where('teamAttendanceSnapshot.on_leave_count', 1)
            ->where('teamAttendanceSnapshot.clocked_in_count', 0)
        );
    }

    public function test_delegate_approver_can_see_delegated_pending_approvals(): void
    {
        // 建立待主管審核的單據
        $formRequest = FormRequest::create([
            'request_no' => 'REQ-TEST-001',
            'form_id' => $this->leaveForm->id,
            'user_id' => $this->employee->id,
            'title' => '員工送出審核單',
            'data' => ['days' => 1],
            'status' => 'pending',
            'current_stage' => 1,
        ]);

        ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'approver_id' => $this->manager->id,
            'stage' => 1,
            'status' => 'pending',
        ]);

        // 主管代理人設定給 employee
        Delegation::create([
            'user_id' => $this->manager->id,
            'delegate_id' => $this->employee->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => '主管出差公出代理',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.pendingApprovalsCount', 1)
            ->has('pendingApprovals', 1)
            ->where('pendingApprovals.0.is_delegated', true)
        );
    }
}
