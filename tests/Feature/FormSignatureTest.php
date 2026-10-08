<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest as EipFormRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $employee;
    protected Department $department;
    protected Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '法務與稽核部',
            'code' => 'LEGAL',
        ]);

        $this->manager = User::factory()->create([
            'name' => '法務主管王大明',
            'email' => 'legal.mgr@eip.test',
            'role' => 'manager',
            'status' => 'active',
            'department_id' => $this->department->id,
            'phone' => '3001',
        ]);

        $this->employee = User::factory()->create([
            'name' => '採購專員陳小美',
            'email' => 'procurement@eip.test',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
            'phone' => '3002',
        ]);

        $this->form = Form::create([
            'name' => '保密協議與法律公文審核單',
            'code' => 'LEGAL_NDA',
            'fields_schema' => [
                ['name' => 'partner_company', 'type' => 'text', 'label' => '合作企業名稱'],
                ['name' => 'amount', 'type' => 'number', 'label' => '專案合約金額'],
            ],
            'is_active' => true,
        ]);
    }

    public function test_approver_can_sign_with_digital_handwritten_signature(): void
    {
        // 1. 建立送審單據
        $formRequest = EipFormRequest::create([
            'form_id' => $this->form->id,
            'user_id' => $this->employee->id,
            'request_no' => 'REQ-SIGN-001',
            'title' => '台積電供應商保密協議審核',
            'data' => [
                'partner_company' => '台灣積體電路製造股份有限公司',
                'amount' => 5000000,
            ],
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => 1,
            'workflow_snapshot' => [
                ['step' => 1, 'title' => '法務主管決行', 'approver_id' => $this->manager->id],
            ],
        ]);

        $record = ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => 1,
            'step_title' => '法務主管決行',
            'approver_id' => $this->manager->id,
            'status' => 'pending',
        ]);

        // 模擬手寫簽名 Base64 DataURL
        $fakeSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // 2. 主管進行簽核核准並附上手寫數位簽章
        $response = $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '條款合規無疑慮，已親筆手寫核簽。',
                'signature' => $fakeSignature,
            ]);

        $response->assertRedirect();

        // 3. 驗證資料庫簽核紀錄已正確儲存簽名
        $record->refresh();
        $this->assertEquals('approved', $record->status);
        $this->assertEquals('條款合規無疑慮，已親筆手寫核簽。', $record->comment);
        $this->assertEquals($fakeSignature, $record->signature);
        $this->assertNotNull($record->actioned_at);

        // 4. 檢驗 Show 頁面與 Print 列印頁面
        $showResponse = $this->actingAs($this->manager)
            ->get(route('forms.show', $formRequest->id));
        $showResponse->assertOk();

        $printResponse = $this->actingAs($this->manager)
            ->get(route('forms.print', $formRequest->id));
        $printResponse->assertOk();
    }

    public function test_approval_without_signature_works_normally(): void
    {
        $formRequest = EipFormRequest::create([
            'form_id' => $this->form->id,
            'user_id' => $this->employee->id,
            'request_no' => 'REQ-SIGN-002',
            'title' => '一般法律諮詢單',
            'data' => ['partner_company' => '內部專案'],
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => 1,
        ]);

        $record = ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => 1,
            'step_title' => '主管審查',
            'approver_id' => $this->manager->id,
            'status' => 'pending',
        ]);

        // 未提供 signature
        $response = $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '同意辦理。',
            ]);

        $response->assertRedirect();
        $record->refresh();
        $this->assertEquals('approved', $record->status);
        $this->assertNull($record->signature);
    }
}
