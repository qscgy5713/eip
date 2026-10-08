<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormCollaborativeApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $employee;
    protected User $hrUser;
    protected Form $leaveForm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $this->admin = User::where('role', 'admin')->first();
        $this->manager = User::where('role', 'manager')->first();
        $this->employee = User::where('role', 'employee')->first();
        $this->hrUser = User::where('role', 'hr')->first();

        $this->leaveForm = Form::where('code', 'LEAVE')->first();
    }

    public function test_manager_can_transfer_approval_to_another_manager_and_be_approved(): void
    {
        // 1. 員工發起請假申請
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '私人事假 2 天',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-11',
                'days' => 2,
                'reason' => '家庭事務處理',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();
        $this->assertEquals('pending', $formRequest->status);
        $this->assertEquals(1, $formRequest->current_step);

        $initialRecord = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('step', 1)
            ->where('status', 'pending')
            ->first();
        $this->assertEquals($this->manager->id, $initialRecord->approver_id);

        // 2. 主管將審批轉簽 (Transfer) 給 HR
        $transferResponse = $this->actingAs($this->manager)->post(route('forms.transfer', $formRequest->id), [
            'target_user_id' => $this->hrUser->id,
            'reason' => '人事特殊規範，轉交 HR 專員決行',
        ]);

        $transferResponse->assertRedirect();
        $transferResponse->assertSessionHas('success');

        // 3. 驗證舊記錄與新記錄狀態
        $initialRecord->refresh();
        $this->assertEquals('transferred', $initialRecord->status);
        $this->assertEquals($this->hrUser->id, $initialRecord->transferred_to_id);
        $this->assertStringContainsString('轉簽派審', $initialRecord->comment);

        $transferredRecord = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('step', 1)
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($transferredRecord);
        $this->assertEquals($this->hrUser->id, $transferredRecord->approver_id);
        $this->assertEquals($this->manager->id, $transferredRecord->transferred_from_id);

        // 4. 驗證稽核日誌與系統通知
        $this->assertTrue(AuditLog::where('action', 'transfer_form_approval')->exists());
        $this->assertGreaterThan(0, $this->hrUser->notifications()->count());

        // 5. 舊主管不再擁有 pending 審核權限
        $failApprove = $this->actingAs($this->manager)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '我想再審一次',
        ]);
        $failApprove->assertStatus(403);

        // 6. 受轉簽的新主管可成功審批核准結案
        $approveResponse = $this->actingAs($this->hrUser)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => 'HR 已核實規範，准假同意',
        ]);
        $approveResponse->assertRedirect();

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);
    }

    public function test_cannot_transfer_approval_to_oneself(): void
    {
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '事假 1 天',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-10',
                'days' => 1,
                'reason' => '事由',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();

        // 嘗試轉簽給自己
        $response = $this->actingAs($this->manager)->post(route('forms.transfer', $formRequest->id), [
            'target_user_id' => $this->manager->id,
            'reason' => '轉給自己',
        ]);

        $response->assertSessionHasErrors(['target_user_id']);
    }

    public function test_unauthorized_user_cannot_transfer_approval(): void
    {
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '事假 1 天',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-10',
                'days' => 1,
                'reason' => '事由',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();

        // 申請人本人或非當前關卡審核人嘗試轉簽 -> 應被拒絕 403
        $response = $this->actingAs($this->employee)->post(route('forms.transfer', $formRequest->id), [
            'target_user_id' => $this->admin->id,
            'reason' => '越級轉派',
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_can_add_sign_collaborator_and_proceed_main_workflow(): void
    {
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '婚假 2 天',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-11',
                'days' => 2,
                'reason' => '婚宴安排',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();

        // 1. 主管發起會辦加簽給 Admin 徵詢意見
        $addSignResponse = $this->actingAs($this->manager)->post(route('forms.add-sign', $formRequest->id), [
            'target_user_id' => $this->admin->id,
            'reason' => '請協助確認跨單位輪值交接狀態',
        ]);

        $addSignResponse->assertRedirect();
        $addSignResponse->assertSessionHas('success');

        // 驗證加簽記錄產生
        $addSignRecord = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('is_add_sign', true)
            ->where('approver_id', $this->admin->id)
            ->first();

        $this->assertNotNull($addSignRecord);
        $this->assertEquals('pending', $addSignRecord->status);
        $this->assertEquals($this->manager->id, $addSignRecord->add_signed_by_id);

        // 主表單狀態仍為 pending
        $formRequest->refresh();
        $this->assertEquals('pending', $formRequest->status);

        // 2. 防呆測試：不得重複加簽同一人（已在 pending 狀態）
        $duplicateResponse = $this->actingAs($this->manager)->post(route('forms.add-sign', $formRequest->id), [
            'target_user_id' => $this->admin->id,
            'reason' => '再次邀請加簽',
        ]);
        $duplicateResponse->assertSessionHasErrors(['target_user_id']);

        // 3. 加簽人 (Admin) 登入進行加簽意見簽署
        $actionResponse = $this->actingAs($this->admin)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '會辦意見：交接已排妥，無排程衝突',
        ]);
        $actionResponse->assertRedirect();

        $addSignRecord->refresh();
        $this->assertEquals('approved', $addSignRecord->status);
        $this->assertEquals('會辦意見：交接已排妥，無排程衝突', $addSignRecord->comment);

        // 加簽完成後，主表單仍應維持 pending（因為主管本人尚未決行）
        $formRequest->refresh();
        $this->assertEquals('pending', $formRequest->status);

        // 驗證通知已送回發起加簽之主管
        $this->assertGreaterThan(0, $this->manager->notifications()->count());

        // 4. 主管進行最終審批決行
        $finalApprove = $this->actingAs($this->manager)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '參酌會辦意見後予以核准',
        ]);
        $finalApprove->assertRedirect();

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);
    }

    public function test_cannot_add_sign_oneself(): void
    {
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '事假 1 天',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-10',
                'days' => 1,
                'reason' => '事由',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();

        // 主管嘗試加簽給自己
        $response = $this->actingAs($this->manager)->post(route('forms.add-sign', $formRequest->id), [
            'target_user_id' => $this->manager->id,
            'reason' => '加簽給自己',
        ]);

        $response->assertSessionHasErrors(['target_user_id']);
    }

    public function test_add_signed_record_rejection_notes_recorded_without_terminating_main_workflow(): void
    {
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '婚假 2 天',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-11',
                'days' => 2,
                'reason' => '婚宴安排',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();

        // 主管發起會辦加簽
        $this->actingAs($this->manager)->post(route('forms.add-sign', $formRequest->id), [
            'target_user_id' => $this->admin->id,
            'reason' => '跨組協調加簽',
        ]);

        $addSignRecord = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('is_add_sign', true)
            ->where('approver_id', $this->admin->id)
            ->first();

        // 加簽人員簽署反對/保留意見
        $actionResponse = $this->actingAs($this->admin)->post(route('forms.action', $formRequest->id), [
            'status' => 'rejected',
            'comment' => '會辦意見：排班人力吃緊，建請評估延期',
        ]);
        $actionResponse->assertRedirect();

        $addSignRecord->refresh();
        $this->assertEquals('rejected', $addSignRecord->status);
        $this->assertEquals('會辦意見：排班人力吃緊，建請評估延期', $addSignRecord->comment);

        // 加簽反對不應終止主流程，主表單仍應為 pending
        $formRequest->refresh();
        $this->assertEquals('pending', $formRequest->status);

        // 主管參考意見後仍可自行裁量決行核准
        $finalApprove = $this->actingAs($this->manager)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '已協調調班，准假',
        ]);
        $finalApprove->assertRedirect();

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);
    }
}
