<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $hr;
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
            'name' => '系統管理員',
            'role' => 'admin',
            'department_id' => $this->department->id,
        ]);

        $this->hr = User::factory()->create([
            'name' => '人資主管',
            'role' => 'hr',
            'department_id' => $this->department->id,
        ]);

        $this->manager = User::factory()->create([
            'name' => '研發主管',
            'role' => 'manager',
            'department_id' => $this->department->id,
        ]);

        $this->employee = User::factory()->create([
            'name' => '研發工程師',
            'role' => 'employee',
            'department_id' => $this->department->id,
        ]);

        $this->leaveForm = Form::create([
            'name' => '休假申請單',
            'code' => 'LEAVE',
            'description' => '請假申請',
            'is_active' => true,
            'fields_schema' => [
                ['key' => 'leave_type', 'label' => '假別', 'type' => 'select', 'options' => ['特休假', '事假', '病假', '補休']],
                ['key' => 'start_date', 'label' => '開始日期', 'type' => 'date'],
                ['key' => 'end_date', 'label' => '結束日期', 'type' => 'date'],
                ['key' => 'days', 'label' => '請假天數', 'type' => 'number'],
                ['key' => 'reason', 'label' => '請假事由', 'type' => 'textarea'],
            ],
        ]);
    }

    public function test_authenticated_user_can_view_leave_balances_page(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('leave-balances.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('LeaveBalances/Index')
            ->has('myBalances')
            ->where('canManage', false)
        );

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $this->employee->id,
            'leave_type' => 'annual',
        ]);
    }

    public function test_regular_employee_cannot_update_leave_quota(): void
    {
        $response = $this->actingAs($this->employee)
            ->put(route('leave-balances.update', $this->manager->id), [
                'year' => 2026,
                'leave_type' => 'annual',
                'allocated_days' => 20,
            ]);

        $response->assertStatus(403);
    }

    public function test_hr_and_admin_can_update_user_quota(): void
    {
        $response = $this->actingAs($this->hr)
            ->put(route('leave-balances.update', $this->employee->id), [
                'year' => 2026,
                'leave_type' => 'annual',
                'allocated_days' => 14.0,
                'note' => '年資滿三年特休調整',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $this->employee->id,
            'year' => 2026,
            'leave_type' => 'annual',
            'allocated_days' => 14.0,
            'note' => '年資滿三年特休調整',
        ]);
    }

    public function test_admin_can_batch_init_yearly_leave_balances(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('leave-balances.batchInit'), [
                'year' => 2027,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $this->employee->id,
            'year' => 2027,
            'leave_type' => 'annual',
            'allocated_days' => 7.0,
        ]);
    }

    public function test_leave_request_within_quota_holds_pending_balance(): void
    {
        // 確保初始額度為 7 天特休
        app(LeaveBalanceService::class)->initUserBalances($this->employee, (int) date('Y'));

        $response = $this->actingAs($this->employee)
            ->post(route('forms.store', $this->leaveForm->id), [
                'title' => '私人事務請休假',
                'data' => [
                    'leave_type' => '特休假',
                    'start_date' => date('Y-m-d'),
                    'end_date' => date('Y-m-d'),
                    'days' => 2.0,
                    'reason' => '家庭私人事務',
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('form_requests', [
            'user_id' => $this->employee->id,
            'title' => '私人事務請休假',
        ]);

        $balance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type', 'annual')
            ->first();

        $this->assertEquals(2.0, $balance->pending_days);
        $this->assertEquals(0.0, $balance->used_days);
        $this->assertEquals(5.0, $balance->available_days);
    }

    public function test_leave_request_exceeding_quota_is_rejected(): void
    {
        // 初始額度為 7 天特休
        app(LeaveBalanceService::class)->initUserBalances($this->employee, (int) date('Y'));

        $response = $this->actingAs($this->employee)
            ->from(route('forms.create', $this->leaveForm->id))
            ->post(route('forms.store', $this->leaveForm->id), [
                'title' => '長途旅行特休',
                'data' => [
                    'leave_type' => '特休假',
                    'start_date' => date('Y-m-d'),
                    'end_date' => date('Y-m-d', strtotime('+15 days')),
                    'days' => 10.0, // 超出 7 天
                    'reason' => '出國度假',
                ],
            ]);

        $response->assertRedirect(route('forms.create', $this->leaveForm->id));
        $response->assertSessionHasErrors('data.days');

        $this->assertDatabaseMissing('form_requests', [
            'user_id' => $this->employee->id,
            'title' => '長途旅行特休',
        ]);

        $balance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type', 'annual')
            ->first();

        $this->assertEquals(0.0, $balance->pending_days);
    }

    public function test_approving_leave_request_converts_pending_to_used(): void
    {
        app(LeaveBalanceService::class)->initUserBalances($this->employee, (int) date('Y'));

        // 提單 2 天
        $this->actingAs($this->employee)
            ->post(route('forms.store', $this->leaveForm->id), [
                'title' => '請假 2 天',
                'data' => [
                    'leave_type' => '特休假',
                    'start_date' => date('Y-m-d'),
                    'end_date' => date('Y-m-d'),
                    'days' => 2.0,
                    'reason' => '休假',
                ],
            ]);

        $formRequest = FormRequest::where('user_id', $this->employee->id)->first();
        $pendingRecord = $formRequest->approvalRecords()->where('status', 'pending')->first();

        // 指定審核人核准
        $this->actingAs($pendingRecord->approver)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '准假，好好休息',
            ]);

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);

        $balance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type', 'annual')
            ->first();

        $this->assertEquals(0.0, $balance->pending_days);
        $this->assertEquals(2.0, $balance->used_days);
        $this->assertEquals(5.0, $balance->available_days);
    }

    public function test_rejecting_leave_request_releases_pending_balance(): void
    {
        app(LeaveBalanceService::class)->initUserBalances($this->employee, (int) date('Y'));

        // 提單 2 天
        $this->actingAs($this->employee)
            ->post(route('forms.store', $this->leaveForm->id), [
                'title' => '請假 2 天',
                'data' => [
                    'leave_type' => '特休假',
                    'start_date' => date('Y-m-d'),
                    'end_date' => date('Y-m-d'),
                    'days' => 2.0,
                    'reason' => '休假',
                ],
            ]);

        $formRequest = FormRequest::where('user_id', $this->employee->id)->first();
        $pendingRecord = $formRequest->approvalRecords()->where('status', 'pending')->first();

        // 指定審核人退件駁回
        $this->actingAs($pendingRecord->approver)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'rejected',
                'comment' => '專案上線期間，暫不准休假',
            ]);

        $formRequest->refresh();
        $this->assertEquals('rejected', $formRequest->status);

        $balance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type', 'annual')
            ->first();

        $this->assertEquals(0.0, $balance->pending_days);
        $this->assertEquals(0.0, $balance->used_days);
        $this->assertEquals(7.0, $balance->available_days);
    }
}
