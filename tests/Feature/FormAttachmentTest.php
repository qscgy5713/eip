<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected User $otherUser;
    protected Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $department = Department::first();

        $this->manager = User::factory()->create([
            'name' => '測試主管',
            'role' => 'manager',
            'department_id' => $department->id,
        ]);

        $this->owner = User::factory()->create([
            'name' => '申請同仁',
            'role' => 'employee',
            'department_id' => $department->id,
        ]);

        $otherDept = Department::skip(1)->first() ?? Department::create(['name' => '行銷企劃部', 'code' => 'MKT']);
        $this->otherUser = User::factory()->create([
            'name' => '其他無關同仁',
            'role' => 'employee',
            'department_id' => $otherDept->id,
        ]);

        $this->form = Form::first();
    }

    public function test_user_can_submit_form_request_with_attachments(): void
    {
        Storage::fake('public');

        $pdfFile = UploadedFile::fake()->create('診斷證明書.pdf', 500, 'application/pdf');
        $imgFile = UploadedFile::fake()->image('收據證明.png', 400, 300);

        $response = $this->actingAs($this->owner)->post(route('forms.store', $this->form->id), [
            'title' => '病假申請（檢附就醫證明）',
            'data' => [
                'reason' => '突發重感冒前往診所就醫',
                'days' => 2,
            ],
            'attachments' => [
                $pdfFile,
                $imgFile,
            ],
        ]);

        $formRequest = FormRequest::where('user_id', $this->owner->id)->latest('id')->first();
        $this->assertNotNull($formRequest);
        $response->assertRedirect(route('forms.show', $formRequest->id));
        $this->assertCount(2, $formRequest->attachments);

        $this->assertEquals('診斷證明書.pdf', $formRequest->attachments[0]['name']);
        $this->assertEquals('收據證明.png', $formRequest->attachments[1]['name']);

        Storage::disk('public')->assertExists($formRequest->attachments[0]['path']);
        Storage::disk('public')->assertExists($formRequest->attachments[1]['path']);
    }

    public function test_user_can_submit_form_request_without_attachments(): void
    {
        $response = $this->actingAs($this->owner)->post(route('forms.store', $this->form->id), [
            'title' => '一般請假申請（無附件）',
            'data' => [
                'reason' => '事假外出處理私事',
                'days' => 1,
            ],
        ]);

        $formRequest = FormRequest::where('user_id', $this->owner->id)->latest('id')->first();
        $this->assertNotNull($formRequest);
        $response->assertRedirect(route('forms.show', $formRequest->id));
        $this->assertEmpty($formRequest->attachments);
    }

    public function test_owner_can_download_attachment(): void
    {
        Storage::fake('public');

        $storedPath = 'form_attachments/test_proof.pdf';
        Storage::disk('public')->put($storedPath, '診斷書內容存證測試');

        $formRequest = FormRequest::create([
            'form_id' => $this->form->id,
            'user_id' => $this->owner->id,
            'request_no' => 'REQ-ATTACH-001',
            'title' => '請假附件下載測試',
            'status' => 'pending',
            'current_step' => 1,
            'data' => ['reason' => '休假'],
            'attachments' => [
                [
                    'name' => '診斷證明.pdf',
                    'path' => $storedPath,
                    'size' => 1024,
                    'mime_type' => 'application/pdf',
                    'uploaded_at' => now()->toISOString(),
                ],
            ],
        ]);

        $response = $this->actingAs($this->owner)->get(route('forms.attachments.download', [$formRequest->id, 0]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString(rawurlencode('診斷證明.pdf'), $response->headers->get('content-disposition'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action' => 'download_form_attachment',
            'auditable_type' => FormRequest::class,
            'auditable_id' => $formRequest->id,
        ]);
    }

    public function test_approver_manager_can_download_attachment(): void
    {
        Storage::fake('public');

        $storedPath = 'form_attachments/manager_review.pdf';
        Storage::disk('public')->put($storedPath, '主管簽核審閱附件內容');

        $formRequest = FormRequest::create([
            'form_id' => $this->form->id,
            'user_id' => $this->owner->id,
            'request_no' => 'REQ-ATTACH-002',
            'title' => '主管簽核請假證明',
            'status' => 'pending',
            'current_step' => 1,
            'data' => ['reason' => '傷病請假'],
            'attachments' => [
                [
                    'name' => '就醫單據.pdf',
                    'path' => $storedPath,
                    'size' => 2048,
                    'mime_type' => 'application/pdf',
                    'uploaded_at' => now()->toISOString(),
                ],
            ],
        ]);

        // 主管下載
        $response = $this->actingAs($this->manager)->get(route('forms.attachments.download', [$formRequest->id, 0]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString(rawurlencode('就醫單據.pdf'), $response->headers->get('content-disposition'));
    }

    public function test_unauthorized_user_cannot_download_attachment(): void
    {
        Storage::fake('public');

        $storedPath = 'form_attachments/secret.pdf';
        Storage::disk('public')->put($storedPath, '機密文件');

        $formRequest = FormRequest::create([
            'form_id' => $this->form->id,
            'user_id' => $this->owner->id,
            'request_no' => 'REQ-ATTACH-003',
            'title' => '私人請假記錄',
            'status' => 'pending',
            'current_step' => 1,
            'data' => ['reason' => '隱私請假'],
            'attachments' => [
                [
                    'name' => '機密診斷.pdf',
                    'path' => $storedPath,
                    'size' => 1024,
                    'mime_type' => 'application/pdf',
                    'uploaded_at' => now()->toISOString(),
                ],
            ],
        ]);

        // 他部同仁無權限調閱應回傳 403 Forbidden
        $response = $this->actingAs($this->otherUser)->get(route('forms.attachments.download', [$formRequest->id, 0]));

        $response->assertForbidden();
    }

    public function test_invalid_attachment_index_returns_404(): void
    {
        $formRequest = FormRequest::create([
            'form_id' => $this->form->id,
            'user_id' => $this->owner->id,
            'request_no' => 'REQ-ATTACH-004',
            'title' => '無附件單據',
            'status' => 'pending',
            'current_step' => 1,
            'data' => [],
            'attachments' => [],
        ]);

        $response = $this->actingAs($this->owner)->get(route('forms.attachments.download', [$formRequest->id, 99]));

        $response->assertNotFound();
    }

    public function test_attachment_upload_validates_file_types_and_size(): void
    {
        Storage::fake('public');

        $badFile = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->owner)->post(route('forms.store', $this->form->id), [
            'title' => '惡意檔案上傳測試',
            'data' => [],
            'attachments' => [
                $badFile,
            ],
        ]);

        $response->assertSessionHasErrors('attachments.0');
    }
}
