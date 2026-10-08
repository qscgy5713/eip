<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $employee;
    protected User $otherEmployee;
    protected Department $department;
    protected Form $leaveForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '技術研發處',
            'code' => 'RND',
        ]);

        $this->admin = User::factory()->create([
            'name' => '系統管理員',
            'role' => 'admin',
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'name' => '研發主管',
            'role' => 'manager',
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $this->employee = User::factory()->create([
            'name' => '一般同仁A',
            'role' => 'employee',
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $this->otherEmployee = User::factory()->create([
            'name' => '其他同仁B',
            'role' => 'employee',
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $this->department->update(['manager_id' => $this->manager->id]);

        $this->leaveForm = Form::firstOrCreate(
            ['code' => 'LEAVE'],
            [
                'name' => '休假申請單',
                'category' => 'hr',
                'description' => '同仁請假申請單',
                'schema' => [
                    ['name' => 'leave_type', 'type' => 'select', 'label' => '假別'],
                    ['name' => 'start_date', 'type' => 'date', 'label' => '開始日期'],
                    ['name' => 'end_date', 'type' => 'date', 'label' => '結束日期'],
                    ['name' => 'days', 'type' => 'number', 'label' => '天數'],
                ],
                'is_active' => true,
            ]
        );

        // 初始化員工特休額度 7 天
        LeaveBalance::create([
            'user_id' => $this->employee->id,
            'year' => (int) date('Y'),
            'leave_type' => 'annual',
            'allocated_days' => 7.0,
            'used_days' => 0.0,
            'pending_days' => 0.0,
        ]);
    }

    public function test_applicant_can_withdraw_pending_leave_request_and_release_held_balance(): void
    {
        // 1. 同仁提出請假申請單 (請 2 天特休)
        $today = now()->toDateString();
        $response = $this->actingAs($this->employee)
            ->post(route('forms.store', $this->leaveForm->id), [
                'title' => '私人事務請特休2天',
                'data' => [
                    'leave_type' => 'annual',
                    'start_date' => $today,
                    'end_date' => $today,
                    'days' => 2,
                    'reason' => '家庭旅遊',
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $formRequest = FormRequest::where('user_id', $this->employee->id)->latest()->first();
        $this->assertNotNull($formRequest);
        $this->assertEquals('pending', $formRequest->status);

        // 檢查特休已凍結 2 天
        $balance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type', 'annual')
            ->first();
        $this->assertEquals(2.0, (float) $balance->pending_days);
        $this->assertEquals(5.0, (float) $balance->available_days);

        // 主管或管理員有一筆待審核記錄
        $pendingApproval = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($pendingApproval);
        $this->assertContains($pendingApproval->approver_id, [$this->manager->id, $this->admin->id]);

        // 2. 申請人主動撤回申請單
        $withdrawResponse = $this->actingAs($this->employee)
            ->post(route('forms.withdraw', $formRequest->id), [
                'reason' => '行程臨時取消，故撤回單據',
            ]);

        $withdrawResponse->assertRedirect(route('forms.show', $formRequest->id));
        $withdrawResponse->assertSessionHas('success');

        // 3. 驗證單據狀態變更為 withdrawn
        $formRequest->refresh();
        $this->assertEquals('withdrawn', $formRequest->status);

        // 4. 驗證凍結額度已完全釋放，可用天數恢復為 7 天
        $balance->refresh();
        $this->assertEquals(0.0, (float) $balance->pending_days);
        $this->assertEquals(0.0, (float) $balance->used_days);
        $this->assertEquals(7.0, (float) $balance->available_days);

        // 5. 驗證原本主管待審記錄已作廢標記為 withdrawn，且備註撤回原因
        $pendingApproval->refresh();
        $this->assertEquals('withdrawn', $pendingApproval->status);
        $this->assertStringContainsString('行程臨時取消', $pendingApproval->comment);

        // 主管待辦清單中已無此單據
        $this->assertDatabaseMissing('approval_records', [
            'form_request_id' => $formRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_unauthorized_user_cannot_withdraw_others_request(): void
    {
        $formRequest = FormRequest::create([
            'request_no' => 'REQ-TEST-W01',
            'form_id' => $this->leaveForm->id,
            'user_id' => $this->employee->id,
            'title' => '同仁A的申請單',
            'data' => ['days' => 1],
            'status' => 'pending',
            'current_stage' => 1,
        ]);

        // 其他同仁嘗試撤回，觸發 403
        $response = $this->actingAs($this->otherEmployee)
            ->post(route('forms.withdraw', $formRequest->id), [
                'reason' => '惡意撤回',
            ]);

        $response->assertStatus(403);
        $formRequest->refresh();
        $this->assertEquals('pending', $formRequest->status);
    }

    public function test_admin_can_withdraw_pending_request_on_behalf(): void
    {
        $formRequest = FormRequest::create([
            'request_no' => 'REQ-TEST-W02',
            'form_id' => $this->leaveForm->id,
            'user_id' => $this->employee->id,
            'title' => '同仁A送出的單據',
            'data' => ['days' => 1],
            'status' => 'pending',
            'current_stage' => 1,
        ]);

        ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'approver_id' => $this->manager->id,
            'step' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('forms.withdraw', $formRequest->id), [
                'reason' => '管理員系統代為撤銷作廢',
            ]);

        $response->assertRedirect(route('forms.show', $formRequest->id));
        $formRequest->refresh();
        $this->assertEquals('withdrawn', $formRequest->status);
    }

    public function test_cannot_withdraw_already_completed_request(): void
    {
        $approvedRequest = FormRequest::create([
            'request_no' => 'REQ-TEST-W03',
            'form_id' => $this->leaveForm->id,
            'user_id' => $this->employee->id,
            'title' => '已核准之請假單',
            'data' => ['days' => 1],
            'status' => 'approved',
            'current_stage' => 1,
        ]);

        $response = $this->actingAs($this->employee)
            ->post(route('forms.withdraw', $approvedRequest->id), [
                'reason' => '嘗試撤回已核准單據',
            ]);

        $response->assertStatus(422);
        $approvedRequest->refresh();
        $this->assertEquals('approved', $approvedRequest->status);
    }

    public function test_copy_from_prefills_data_for_reapplying(): void
    {
        $withdrawnRequest = FormRequest::create([
            'request_no' => 'REQ-TEST-W04',
            'form_id' => $this->leaveForm->id,
            'user_id' => $this->employee->id,
            'title' => '已撤回的單據',
            'data' => [
                'leave_type' => 'annual',
                'reason' => '原行程請假事由',
                'days' => 2,
            ],
            'status' => 'withdrawn',
            'current_stage' => 1,
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('forms.create', [
                'form' => $this->leaveForm->id,
                'copy_from' => $withdrawnRequest->id,
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Forms/Create')
            ->where('prefillData.reason', '原行程請假事由')
            ->where('prefillData.leave_type', 'annual')
        );
    }
}
