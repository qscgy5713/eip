<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Form;
use App\Models\FormRequest as EipFormRequest;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Models\Webhook;
use App\Services\CsvExportService;
use App\Services\LeaveBalanceService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $hrUser;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $dept = Department::create([
            'name' => '資訊安全部',
            'code' => 'SEC',
            'sort_order' => 1,
        ]);

        $this->admin = User::factory()->create([
            'name' => '資安長',
            'email' => 'ciso@eip.local',
            'role' => 'admin',
            'department_id' => $dept->id,
            'status' => 'active',
        ]);

        $this->hrUser = User::factory()->create([
            'name' => '人資專員',
            'email' => 'hr_sec@eip.local',
            'role' => 'hr',
            'department_id' => $dept->id,
            'status' => 'active',
        ]);

        $this->employee = User::factory()->create([
            'name' => '一般同仁',
            'email' => 'emp_sec@eip.local',
            'role' => 'employee',
            'department_id' => $dept->id,
            'status' => 'active',
        ]);
    }

    /**
     * SEC-02: Scoped Route Model Binding - 禁止跨文件調閱不屬於該文件的歷史版本
     */
    public function test_scoped_route_model_binding_prevents_cross_document_version_access(): void
    {
        Storage::fake('local');

        // 文件 A (公開)
        $docA = Document::create([
            'title' => '員工手冊',
            'category' => 'policy',
            'uploader_id' => $this->admin->id,
            'current_version' => 1,
        ]);
        $verA = DocumentVersion::create([
            'document_id' => $docA->id,
            'uploader_id' => $this->admin->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => 'documents/handbook_v1.pdf',
            'file_name' => 'handbook_v1.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('local')->put('documents/handbook_v1.pdf', 'handbook content');

        // 文件 B (機密，僅限 admin)
        $docB = Document::create([
            'title' => '核心財務報表',
            'category' => 'policy',
            'uploader_id' => $this->admin->id,
            'current_version' => 1,
            'restricted_roles' => ['admin'],
        ]);
        $verB = DocumentVersion::create([
            'document_id' => $docB->id,
            'uploader_id' => $this->admin->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => 'documents/finance_secret.pdf',
            'file_name' => 'finance_secret.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('local')->put('documents/finance_secret.pdf', 'secret finance data');

        // 一般員工嘗試將 docA 搭配 verB 下載或預覽，必須被 404 攔截
        $downloadRes = $this->actingAs($this->employee)
            ->get(route('documents.download', ['document' => $docA->id, 'version' => $verB->id]));
        $downloadRes->assertStatus(404);

        $previewRes = $this->actingAs($this->employee)
            ->get(route('documents.preview', ['document' => $docA->id, 'version' => $verB->id]));
        $previewRes->assertStatus(404);
    }

    /**
     * SEC-04: 垂直越權防護 - 人資帳號禁止建立 Admin 角色帳號
     */
    public function test_hr_user_cannot_create_admin_user_vertical_privilege_escalation(): void
    {
        $response = $this->actingAs($this->hrUser)->post(route('org-management.users.store'), [
            'name' => '惡意提權管理員',
            'email' => 'malicious_admin@eip.local',
            'password' => 'Password123!',
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'malicious_admin@eip.local']);

        // 但 Admin 本身可以建立 Admin
        $adminRes = $this->actingAs($this->admin)->post(route('org-management.users.store'), [
            'name' => '合法副總裁',
            'email' => 'legit_admin@eip.local',
            'password' => 'Password123!',
            'role' => 'admin',
        ]);

        $adminRes->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'legit_admin@eip.local', 'role' => 'admin']);
    }

    /**
     * SEC-03: 全域活躍會話即時撤銷 - 被設為停權或離職後，活躍會話立即強制終止
     */
    public function test_suspended_or_resigned_user_session_is_immediately_revoked_by_middleware(): void
    {
        // 登入正常員工
        $this->actingAs($this->employee);

        // 正常存取首頁
        $res = $this->get(route('dashboard'));
        $res->assertStatus(200);

        // 管理員將其標記為暫時停權 (suspended)
        $this->employee->update(['status' => 'suspended']);

        // 該員工發起下一個請求時，中介層 EnsureUserIsActive 必須即時將其登出並重導向至登入頁
        $subsequentRes = $this->get(route('dashboard'));
        $subsequentRes->assertRedirect(route('login'));
        $subsequentRes->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());
    }

    /**
     * SEC-05: 請假額度並發競態排他防護 - 悲觀鎖二次校驗
     */
    public function test_leave_balance_pessimistic_lock_prevents_overdraft_on_hold_balance(): void
    {
        $leaveBalance = LeaveBalance::create([
            'user_id' => $this->employee->id,
            'year' => (int) date('Y'),
            'leave_type' => LeaveBalance::TYPE_ANNUAL,
            'allocated_days' => 1.0,
            'used_days' => 0.0,
            'pending_days' => 0.0,
        ]);

        $form = Form::create([
            'name' => '特別休假申請單',
            'code' => 'LEAVE',
            'category' => 'hr',
            'workflow_type' => 'conditional',
            'schema' => [],
            'is_active' => true,
        ]);

        // 假單 1：申請 1 天（額度恰好用完）
        $req1 = EipFormRequest::create([
            'form_id' => $form->id,
            'user_id' => $this->employee->id,
            'request_no' => 'REQ-TEST-001',
            'title' => '休假申請 1',
            'data' => ['leave_type' => 'annual', 'days' => 1.0],
            'status' => 'pending',
            'current_step' => 1,
        ]);

        $service = app(LeaveBalanceService::class);
        $this->assertTrue($service->holdBalance($req1));

        $leaveBalance->refresh();
        $this->assertEquals(1.0, (float) $leaveBalance->pending_days);
        $this->assertEquals(0.0, (float) $leaveBalance->available_days);

        // 假單 2：若此時另一個並發請求企圖再 hold 0.5 天，必須觸發 InvalidArgumentException 阻擋透支
        $req2 = EipFormRequest::create([
            'form_id' => $form->id,
            'user_id' => $this->employee->id,
            'request_no' => 'REQ-TEST-002',
            'title' => '休假申請 2',
            'data' => ['leave_type' => 'annual', 'days' => 0.5],
            'status' => 'pending',
            'current_step' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $service->holdBalance($req2);
    }

    /**
     * SEC-06: Webhook SSRF 防護與 Secret 隱藏
     */
    public function test_webhook_ssrf_protection_rejects_private_and_localhost_urls(): void
    {
        // 1. 驗證 isSafeUrl 方法
        $this->assertFalse(WebhookService::isSafeUrl('http://127.0.0.1:8000/webhook'));
        $this->assertFalse(WebhookService::isSafeUrl('http://localhost/webhook'));
        $this->assertFalse(WebhookService::isSafeUrl('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse(WebhookService::isSafeUrl('http://10.0.0.1/notify'));
        $this->assertFalse(WebhookService::isSafeUrl('http://192.168.1.100:8080/hook'));
        $this->assertFalse(WebhookService::isSafeUrl('ftp://example.com/webhook'));
        $this->assertTrue(WebhookService::isSafeUrl('https://hooks.slack.com/services/T00/B00/X00'));

        // 2. 透過 Controller 儲存端點測試拒絕 SSRF URL
        $response = $this->actingAs($this->admin)->post(route('webhooks.store'), [
            'name' => '惡意 SSRF 探測',
            'url' => 'http://127.0.0.1:5432/exploit',
            'events' => ['form.submitted'],
            'secret' => 'supersecret',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertDatabaseMissing('webhooks', ['name' => '惡意 SSRF 探測']);

        // 3. 測試 Secret 在模型序列化時自動隱藏
        $safeWebhook = Webhook::create([
            'name' => '合法外部推播',
            'url' => 'https://discord.com/api/webhooks/123/abc',
            'events' => ['form.submitted'],
            'secret' => 'my-private-signing-key',
            'is_active' => true,
        ]);

        $serialized = $safeWebhook->toArray();
        $this->assertArrayNotHasKey('secret', $serialized);
    }

    /**
     * SEC-07: CSV 公式注入防護 - 特殊字元自動前綴單引號
     */
    public function test_csv_export_sanitizes_formula_injection_characters(): void
    {
        $maliciousCell = '=cmd|\'/C calc\'!A0';
        $sanitizedCell = CsvExportService::sanitizeCell($maliciousCell);
        $this->assertEquals("'=cmd|'/C calc'!A0", $sanitizedCell);

        $row = ['+12345', '-SUM(A1:A10)', '@HYPERLINK("http://evil.com")', '正常文字', null];
        $sanitizedRow = CsvExportService::sanitizeRow($row);

        $this->assertEquals("'+12345", $sanitizedRow[0]);
        $this->assertEquals("'-SUM(A1:A10)", $sanitizedRow[1]);
        $this->assertEquals("'@HYPERLINK(\"http://evil.com\")", $sanitizedRow[2]);
        $this->assertEquals('正常文字', $sanitizedRow[3]);
        $this->assertNull($sanitizedRow[4]);
    }

    /**
     * SEC-01: 機密證明附件與公告附件存儲於 local 私有磁碟與授權下載
     */
    public function test_form_and_announcement_attachments_stored_in_private_disk(): void
    {
        Storage::fake('local');

        $form = Form::create([
            'name' => '病假證明單',
            'code' => 'SICK_LEAVE',
            'category' => 'hr',
            'workflow_type' => 'single',
            'schema' => [],
            'is_active' => true,
        ]);

        // 申請人上傳病歷證明文件
        $file = UploadedFile::fake()->create('medical_diagnosis.pdf', 500, 'application/pdf');

        $res = $this->actingAs($this->employee)->post(route('forms.store', $form->id), [
            'title' => '病假請假檢附證明',
            'data' => ['days' => 1],
            'attachments' => [$file],
        ]);

        $formRequest = EipFormRequest::latest()->first();
        $this->assertNotNull($formRequest);
        $res->assertRedirect(route('forms.show', $formRequest->id));
        $this->assertNotEmpty($formRequest->attachments);

        $savedPath = $formRequest->attachments[0]['path'];
        $this->assertStringStartsWith('private_form_attachments/', $savedPath);
        Storage::disk('local')->assertExists($savedPath);

        // 授權下載測試：申請人可下載，非關聯同仁禁止下載
        $downloadRes = $this->actingAs($this->employee)
            ->get(route('forms.attachments.download', ['formRequest' => $formRequest->id, 'index' => 0]));
        $downloadRes->assertStatus(200);

        $otherUser = User::factory()->create(['status' => 'active', 'role' => 'employee']);
        $unauthorizedRes = $this->actingAs($otherUser)
            ->get(route('forms.attachments.download', ['formRequest' => $formRequest->id, 'index' => 0]));
        $unauthorizedRes->assertStatus(403);
    }
}
