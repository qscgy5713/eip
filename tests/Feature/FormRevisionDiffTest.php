<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest as EipFormRequest;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormRevisionDiffTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $employee;
    protected Department $department;
    protected Form $leaveForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '技術研發部',
            'code' => 'RD',
        ]);

        $this->manager = User::factory()->create([
            'name' => '研發主管',
            'email' => 'rd.manager@eip.test',
            'role' => 'manager',
            'status' => 'active',
            'department_id' => $this->department->id,
            'phone' => '2001',
        ]);

        $this->employee = User::factory()->create([
            'name' => '工程師小陳',
            'email' => 'chen@eip.test',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
            'phone' => '2002',
        ]);

        // 初始化休假額度
        LeaveBalance::create([
            'user_id' => $this->employee->id,
            'leave_type' => 'annual',
            'year' => now()->year,
            'total_days' => 10,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $this->leaveForm = Form::create([
            'name' => '特休請假單',
            'code' => 'LEAVE',
            'fields_schema' => [
                ['name' => 'leave_type', 'type' => 'select', 'label' => '假別'],
                ['name' => 'days', 'type' => 'number', 'label' => '天數'],
                ['name' => 'reason', 'type' => 'textarea', 'label' => '請假事由'],
            ],
            'is_active' => true,
        ]);
    }

    public function test_resubmit_records_revision_history_snapshot(): void
    {
        // 1. 建立初始請假單 (請假 3 天)
        $formRequest = EipFormRequest::create([
            'form_id' => $this->leaveForm->id,
            'user_id' => $this->employee->id,
            'request_no' => 'REQ-DIFF-001',
            'title' => '工程師小陳特休申請單',
            'data' => [
                'leave_type' => 'annual',
                'days' => 3,
                'reason' => '家庭旅遊',
            ],
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => 1,
            'workflow_snapshot' => [
                ['step' => 1, 'title' => '部門主管審核', 'approver_id' => $this->manager->id],
            ],
        ]);

        ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => 1,
            'step_title' => '部門主管審核',
            'approver_id' => $this->manager->id,
            'status' => 'pending',
        ]);

        // 2. 主管執行「退回修改」
        $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'revision_required',
                'comment' => '專案趕工期，請縮短為 2 天並詳述工作代理人。',
            ]);

        $formRequest->refresh();
        $this->assertEquals('revision_required', $formRequest->status);

        // 3. 申請人修改內容並重新提交
        $response = $this->actingAs($this->employee)
            ->post(route('forms.requests.resubmit', $formRequest->id), [
                'data' => [
                    'leave_type' => 'annual',
                    'days' => 2,
                    'reason' => '家庭旅遊（已協調同事小張代理線上值班）',
                ],
                'resubmit_note' => '已依主管指示縮短為 2 天，並完成值班交接。',
            ]);

        $response->assertRedirect(route('forms.show', $formRequest->id));

        $formRequest->refresh();
        $this->assertEquals('pending', $formRequest->status);
        $this->assertNotNull($formRequest->revision_history);
        $this->assertCount(1, $formRequest->revision_history);

        $firstRev = $formRequest->revision_history[0];
        $this->assertEquals(1, $firstRev['version']);
        $this->assertEquals('已依主管指示縮短為 2 天，並完成值班交接。', $firstRev['resubmit_note']);
        $this->assertEquals($this->employee->id, $firstRev['resubmitted_by']['id']);
        $this->assertEquals(3, $firstRev['previous_data']['days']);
        $this->assertEquals(2, $firstRev['new_data']['days']);
        $this->assertEquals('家庭旅遊', $firstRev['previous_data']['reason']);
        $this->assertStringContainsString('已協調同事小張', $firstRev['new_data']['reason']);

        // 4. 第二次退回與第二次修改累積版次
        $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'revision_required',
                'comment' => '請再次確認請假日期。',
            ]);

        $this->actingAs($this->employee)
            ->post(route('forms.requests.resubmit', $formRequest->id), [
                'data' => [
                    'leave_type' => 'annual',
                    'days' => 2,
                    'reason' => '家庭旅遊（10/20~10/21）',
                ],
                'resubmit_note' => '補充確切請假日期。',
            ]);

        $formRequest->refresh();
        $this->assertCount(2, $formRequest->revision_history);
        $secondRev = $formRequest->revision_history[1];
        $this->assertEquals(2, $secondRev['version']);
        $this->assertEquals('補充確切請假日期。', $secondRev['resubmit_note']);

        // 5. 檢驗 Show 頁面正確載入單據與修訂記錄
        $showResponse = $this->actingAs($this->manager)
            ->get(route('forms.show', $formRequest->id));
        $showResponse->assertOk();
    }
}
