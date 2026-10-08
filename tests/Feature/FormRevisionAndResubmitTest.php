<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormRevisionAndResubmitTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;
    private User $manager;
    private User $otherUser;
    private Department $dept;
    private Form $expenseForm;
    private Form $leaveForm;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->dept = Department::create(['name' => '研發一部', 'code' => 'RD1']);

        $this->manager = User::factory()->create([
            'name' => '張處長',
            'role' => 'manager',
            'department_id' => $this->dept->id,
        ]);

        $this->applicant = User::factory()->create([
            'name' => '陳工程師',
            'role' => 'employee',
            'department_id' => $this->dept->id,
        ]);

        $this->otherUser = User::factory()->create([
            'name' => '李同仁',
            'role' => 'employee',
            'department_id' => $this->dept->id,
        ]);

        $this->expenseForm = Form::create([
            'name' => '費用報銷申請單',
            'code' => 'EXPENSE',
            'description' => '各項行政與業務報支',
            'is_active' => true,
        ]);

        $this->leaveForm = Form::create([
            'name' => '特休請假單',
            'code' => 'LEAVE',
            'description' => '員工休假請假',
            'is_active' => true,
        ]);
    }

    public function test_approver_can_request_revision_with_comment(): void
    {
        $formRequest = FormRequest::create([
            'form_id' => $this->expenseForm->id,
            'user_id' => $this->applicant->id,
            'request_no' => 'REQ-EXP-202610-001',
            'title' => '客戶洽談餐敘報支',
            'data' => [
                'amount' => 3500,
                'purpose' => '重要客戶業務開發',
            ],
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => 1,
            'workflow_snapshot' => [
                [
                    'step' => 1,
                    'title' => '直屬主管初審',
                    'approver_id' => $this->manager->id,
                ],
            ],
        ]);

        $record = ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => 1,
            'step_title' => '直屬主管初審',
            'approver_id' => $this->manager->id,
            'status' => 'pending',
        ]);

        // 主管退回修改
        $response = $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'revision_required',
                'comment' => '請補附具統編之發票明細並填寫同仁名單',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $formRequest->refresh();
        $record->refresh();

        $this->assertEquals('revision_required', $formRequest->status);
        $this->assertEquals('returned', $record->status);
        $this->assertEquals('請補附具統編之發票明細並填寫同仁名單', $record->comment);

        // 驗證審計留痕
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'request_form_revision',
            'auditable_id' => $formRequest->id,
        ]);
    }

    public function test_request_revision_requires_comment(): void
    {
        $formRequest = FormRequest::create([
            'form_id' => $this->expenseForm->id,
            'user_id' => $this->applicant->id,
            'request_no' => 'REQ-EXP-202610-002',
            'title' => '辦公耗材採購報支',
            'data' => ['amount' => 1200],
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => 1,
        ]);

        ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => 1,
            'approver_id' => $this->manager->id,
            'status' => 'pending',
        ]);

        // 未填寫退回原因
        $response = $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'revision_required',
                'comment' => '',
            ]);

        $response->assertSessionHasErrors('comment');
    }

    public function test_applicant_can_resubmit_updated_data_and_new_attachment(): void
    {
        $formRequest = FormRequest::create([
            'form_id' => $this->expenseForm->id,
            'user_id' => $this->applicant->id,
            'request_no' => 'REQ-EXP-202610-003',
            'title' => '出差計程車車資',
            'data' => ['amount' => 800, 'purpose' => '舊事由'],
            'status' => 'revision_required',
            'current_step' => 1,
            'total_steps' => 1,
            'workflow_snapshot' => [
                [
                    'step' => 1,
                    'title' => '直屬主管初審',
                    'approver_id' => $this->manager->id,
                ],
            ],
        ]);

        ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => 1,
            'step_title' => '直屬主管初審',
            'approver_id' => $this->manager->id,
            'status' => 'returned',
            'comment' => '請補登乘車起迄點與收據檔案',
        ]);

        $file = UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf');

        // 申請人修改重送
        $response = $this->actingAs($this->applicant)
            ->post(route('forms.requests.resubmit', $formRequest->id), [
                'data' => [
                    'amount' => 850,
                    'purpose' => '台北車站至內湖園區計程車車資',
                ],
                'resubmit_note' => '已補上電子收據並更新確切金額',
                'attachments' => [$file],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $formRequest->refresh();
        $this->assertEquals('pending', $formRequest->status);
        $this->assertEquals(850, $formRequest->data['amount']);
        $this->assertEquals('台北車站至內湖園區計程車車資', $formRequest->data['purpose']);
        $this->assertCount(1, $formRequest->attachments);
        $this->assertEquals('receipt.pdf', $formRequest->attachments[0]['name']);

        // 驗證建立新的待審批記錄
        $newPendingRecord = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($newPendingRecord);
        $this->assertEquals($this->manager->id, $newPendingRecord->approver_id);
        $this->assertStringContainsString('已補上電子收據', $newPendingRecord->comment);

        // 驗證審計留痕
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'resubmit_form_request',
            'auditable_id' => $formRequest->id,
        ]);
    }

    public function test_non_applicant_cannot_resubmit_others_form(): void
    {
        $formRequest = FormRequest::create([
            'form_id' => $this->expenseForm->id,
            'user_id' => $this->applicant->id,
            'request_no' => 'REQ-EXP-202610-004',
            'title' => '他人報銷',
            'data' => ['amount' => 500],
            'status' => 'revision_required',
            'current_step' => 1,
        ]);

        $response = $this->actingAs($this->otherUser)
            ->post(route('forms.requests.resubmit', $formRequest->id), [
                'data' => ['amount' => 600],
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_resubmit_when_not_revision_required(): void
    {
        $formRequest = FormRequest::create([
            'form_id' => $this->expenseForm->id,
            'user_id' => $this->applicant->id,
            'request_no' => 'REQ-EXP-202610-005',
            'title' => '審核中之申請單',
            'data' => ['amount' => 500],
            'status' => 'pending', // 非 revision_required
            'current_step' => 1,
        ]);

        $response = $this->actingAs($this->applicant)
            ->post(route('forms.requests.resubmit', $formRequest->id), [
                'data' => ['amount' => 600],
            ]);

        $response->assertSessionHas('error');
    }

    public function test_leave_request_resubmit_adjusts_pending_balance_safely(): void
    {
        // 建立申請人特休配額 (總額 10 天，目前 pending 2 天)
        $balance = LeaveBalance::create([
            'user_id' => $this->applicant->id,
            'leave_type' => LeaveBalance::TYPE_ANNUAL,
            'allocated_days' => 10,
            'used_days' => 0,
            'pending_days' => 2,
            'year' => now()->year,
        ]);

        $formRequest = FormRequest::create([
            'form_id' => $this->leaveForm->id,
            'user_id' => $this->applicant->id,
            'request_no' => 'REQ-LV-202610-006',
            'title' => '特休請假',
            'data' => [
                'leave_type' => '特休',
                'days' => 2,
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-16',
            ],
            'status' => 'revision_required',
            'current_step' => 1,
            'total_steps' => 1,
            'workflow_snapshot' => [
                [
                    'step' => 1,
                    'title' => '主管審查',
                    'approver_id' => $this->manager->id,
                ],
            ],
        ]);

        // 修改天數由 2 天增加為 3 天
        $response = $this->actingAs($this->applicant)
            ->post(route('forms.requests.resubmit', $formRequest->id), [
                'data' => [
                    'leave_type' => '特休',
                    'days' => 3,
                    'start_date' => '2026-10-15',
                    'end_date' => '2026-10-17',
                ],
                'resubmit_note' => '多請一天特休',
            ]);

        $response->assertRedirect();
        $balance->refresh();

        // pending_days 應由 2 天調整為 3 天
        $this->assertEquals(3.0, (float) $balance->pending_days);
    }

    public function test_resubmitted_form_can_be_subsequently_approved_to_complete(): void
    {
        $formRequest = FormRequest::create([
            'form_id' => $this->expenseForm->id,
            'user_id' => $this->applicant->id,
            'request_no' => 'REQ-EXP-202610-007',
            'title' => '文具報支',
            'data' => ['amount' => 500],
            'status' => 'revision_required',
            'current_step' => 1,
            'total_steps' => 1,
            'workflow_snapshot' => [
                [
                    'step' => 1,
                    'title' => '主管審查',
                    'approver_id' => $this->manager->id,
                ],
            ],
        ]);

        // 1. 申請人補件重送
        $this->actingAs($this->applicant)
            ->post(route('forms.requests.resubmit', $formRequest->id), [
                'data' => ['amount' => 500, 'item' => '白板筆與便利貼'],
                'resubmit_note' => '已補齊品項名稱',
            ]);

        $formRequest->refresh();
        $this->assertEquals('pending', $formRequest->status);

        // 2. 主管核准通過
        $response = $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '補件完整，核准通過',
            ]);

        $response->assertRedirect();
        $formRequest->refresh();

        $this->assertEquals('approved', $formRequest->status);
    }
}
