<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $employee;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->dept = Department::create(['name' => '行政部', 'code' => 'ADM']);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'department_id' => $this->dept->id,
        ]);

        $this->manager = User::factory()->create([
            'role' => 'manager',
            'department_id' => $this->dept->id,
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $this->dept->id,
        ]);
    }

    public function test_authenticated_user_can_view_documents_index(): void
    {
        $response = $this->actingAs($this->employee)->get('/documents');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Documents/Index')
            ->has('documents')
            ->has('departments')
            ->has('filters')
        );
    }

    public function test_user_can_upload_new_document(): void
    {
        $file = UploadedFile::fake()->create('company_rules.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->employee)->post('/documents', [
            'title' => '2026 年度員工手冊',
            'category' => 'policy',
            'description' => '最新版同仁工作守則',
            'department_id' => $this->dept->id,
            'version_label' => 'v1.0',
            'changelog' => '初版上傳發布',
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'title' => '2026 年度員工手冊',
            'category' => 'policy',
            'current_version' => 1,
        ]);

        $this->assertDatabaseHas('document_versions', [
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_name' => 'company_rules.pdf',
        ]);
    }

    public function test_user_can_upload_new_version_to_existing_document(): void
    {
        $doc = Document::create([
            'title' => '差旅費報支辦法',
            'category' => 'policy',
            'uploader_id' => $this->employee->id,
            'current_version' => 1,
        ]);

        DocumentVersion::create([
            'document_id' => $doc->id,
            'uploader_id' => $this->employee->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => 'documents/fake_v1.pdf',
            'file_name' => 'fake_v1.pdf',
            'file_size' => 1024,
        ]);

        $newFile = UploadedFile::fake()->create('travel_policy_v2.pdf', 800, 'application/pdf');

        $response = $this->actingAs($this->employee)->post("/documents/{$doc->id}/versions", [
            'version_label' => 'v2.0',
            'changelog' => '調整住宿補助每日上限',
            'file' => $newFile,
        ]);

        $response->assertRedirect();
        $this->assertEquals(2, $doc->fresh()->current_version);

        $this->assertDatabaseHas('document_versions', [
            'document_id' => $doc->id,
            'version_number' => 2,
            'version_label' => 'v2.0',
            'file_name' => 'travel_policy_v2.pdf',
        ]);
    }

    public function test_user_can_download_public_document_and_increments_count(): void
    {
        $fakePath = 'documents/sample.pdf';
        Storage::disk('local')->put($fakePath, 'Sample file content');

        $doc = Document::create([
            'title' => '公開規章',
            'category' => 'policy',
            'uploader_id' => $this->employee->id,
            'current_version' => 1,
            'download_count' => 0,
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'uploader_id' => $this->employee->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => $fakePath,
            'file_name' => 'sample.pdf',
            'file_size' => 100,
        ]);

        $response = $this->actingAs($this->employee)->get("/documents/{$doc->id}/download/{$ver->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $doc->fresh()->download_count);
    }

    public function test_regular_employee_cannot_download_restricted_document(): void
    {
        $fakePath = 'documents/secret.pdf';
        Storage::disk('local')->put($fakePath, 'Confidential content');

        $doc = Document::create([
            'title' => '董事會機密規章',
            'category' => 'policy',
            'uploader_id' => $this->admin->id,
            'restricted_roles' => ['admin', 'manager'], // 一般員工禁止
            'current_version' => 1,
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'uploader_id' => $this->admin->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => $fakePath,
            'file_name' => 'secret.pdf',
            'file_size' => 100,
        ]);

        // 一般同仁嘗試下載
        $response = $this->actingAs($this->employee)->get("/documents/{$doc->id}/download/{$ver->id}");
        $response->assertStatus(403);

        // 主管下載則成功
        $managerResponse = $this->actingAs($this->manager)->get("/documents/{$doc->id}/download/{$ver->id}");
        $managerResponse->assertStatus(200);
    }

    public function test_uploader_can_delete_document(): void
    {
        $fakePath = 'documents/to_delete.pdf';
        Storage::disk('local')->put($fakePath, 'Delete me');

        $doc = Document::create([
            'title' => '待刪除文件',
            'category' => 'policy',
            'uploader_id' => $this->employee->id,
            'current_version' => 1,
        ]);

        DocumentVersion::create([
            'document_id' => $doc->id,
            'uploader_id' => $this->employee->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => $fakePath,
            'file_name' => 'to_delete.pdf',
            'file_size' => 100,
        ]);

        $response = $this->actingAs($this->employee)->delete("/documents/{$doc->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
        $this->assertFalse(Storage::disk('local')->exists($fakePath));
    }

    public function test_other_user_cannot_delete_others_document(): void
    {
        $doc = Document::create([
            'title' => '陳同仁的私有文件',
            'category' => 'policy',
            'uploader_id' => $this->employee->id,
            'current_version' => 1,
        ]);

        $otherUser = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($otherUser)->delete("/documents/{$doc->id}");
        $response->assertStatus(403);

        $this->assertDatabaseHas('documents', ['id' => $doc->id]);
    }
}
