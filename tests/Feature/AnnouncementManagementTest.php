<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Models\Webhook;
use App\Notifications\EipSystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $department = Department::first();

        $this->admin = User::factory()->create([
            'name' => '企業管理員',
            'role' => 'admin',
            'department_id' => $department->id,
        ]);

        $this->manager = User::factory()->create([
            'name' => '部門主管',
            'role' => 'manager',
            'department_id' => $department->id,
        ]);

        $this->employee = User::factory()->create([
            'name' => '一般基層員工',
            'role' => 'employee',
            'department_id' => $department->id,
        ]);
    }

    public function test_admin_or_manager_can_publish_announcement_with_attachments(): void
    {
        Storage::fake('local');
        Notification::fake();

        $pdfFile = UploadedFile::fake()->create('2026年度規章手冊.pdf', 800, 'application/pdf');
        $imgFile = UploadedFile::fake()->image('活動海報.png', 600, 400);

        $response = $this->actingAs($this->admin)->post(route('announcements.store'), [
            'title' => '2026年年度營運規範與春酒活動通告',
            'category' => 'company',
            'priority' => 'high',
            'content' => '各位同仁請詳閱附件營運規範手冊，並於規定期限前回覆出席意向。',
            'is_pinned' => true,
            'status' => 'published',
            'attachments' => [$pdfFile, $imgFile],
        ]);

        $response->assertRedirect(route('announcements.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('announcements', [
            'title' => '2026年年度營運規範與春酒活動通告',
            'category' => 'company',
            'priority' => 'high',
            'is_pinned' => true,
            'status' => 'published',
        ]);

        $announcement = Announcement::where('title', '2026年年度營運規範與春酒活動通告')->first();
        $this->assertNotNull($announcement);
        $this->assertCount(2, $announcement->attachments);

        $firstAttachment = $announcement->attachments[0];
        $this->assertEquals('2026年度規章手冊.pdf', $firstAttachment['name']);
        Storage::disk('local')->assertExists($firstAttachment['path']);

        $secondAttachment = $announcement->attachments[1];
        $this->assertEquals('活動海報.png', $secondAttachment['name']);
        Storage::disk('local')->assertExists($secondAttachment['path']);

        // 審計日誌確認
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'create_announcement',
            'auditable_type' => Announcement::class,
            'auditable_id' => $announcement->id,
        ]);

        // 重要公告發送系統通知全員
        Notification::assertSentTo(
            $this->employee,
            EipSystemNotification::class,
            function ($notification) {
                return str_contains($notification->title, '重要公告');
            }
        );
    }

    public function test_regular_employee_cannot_publish_announcement(): void
    {
        $response = $this->actingAs($this->employee)->post(route('announcements.store'), [
            'title' => '非法的普通員工發布測試',
            'category' => 'general',
            'priority' => 'normal',
            'content' => '一般同仁不應具備發布權限。',
            'status' => 'published',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('announcements', [
            'title' => '非法的普通員工發布測試',
        ]);
    }

    public function test_employee_can_view_and_safely_download_published_attachment(): void
    {
        Storage::fake('public');

        $fakePath = 'announcements/attachments/fake_guide.pdf';
        Storage::disk('public')->put($fakePath, 'PDF dummy contents');

        $announcement = Announcement::create([
            'title' => '資訊安全作業指引發布',
            'category' => 'general',
            'priority' => 'normal',
            'content' => '請全體同仁遵行資安作業手冊。',
            'author_id' => $this->admin->id,
            'published_at' => now(),
            'is_pinned' => false,
            'status' => 'published',
            'attachments' => [
                [
                    'name' => '資訊安全作業手冊v2.pdf',
                    'path' => $fakePath,
                    'size' => 12345,
                    'mime' => 'application/pdf',
                ],
            ],
        ]);

        // 正常在職同仁調閱詳情頁
        $showResponse = $this->actingAs($this->employee)->get(route('announcements.show', $announcement->id));
        $showResponse->assertOk();

        // 正常在職同仁下載官方附件
        $downloadResponse = $this->actingAs($this->employee)->get(
            route('announcements.attachments.download', ['announcement' => $announcement->id, 'index' => 0])
        );

        $downloadResponse->assertOk();
        $this->assertStringContainsString('attachment', $downloadResponse->headers->get('content-disposition'));
        $this->assertStringContainsString(rawurlencode('資訊安全作業手冊v2.pdf'), $downloadResponse->headers->get('content-disposition'));

        // 審計日誌確認留痕
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->employee->id,
            'action' => 'download_announcement_attachment',
            'auditable_type' => Announcement::class,
            'auditable_id' => $announcement->id,
        ]);
    }

    public function test_employee_cannot_view_or_download_draft_announcement_attachment(): void
    {
        Storage::fake('public');

        $fakePath = 'announcements/attachments/secret_draft.pdf';
        Storage::disk('public')->put($fakePath, 'Secret draft content');

        $draftAnnouncement = Announcement::create([
            'title' => '未發布的內部機密草稿公告',
            'category' => 'company',
            'priority' => 'urgent',
            'content' => '此為草稿，尚未對全體同仁公開。',
            'author_id' => $this->admin->id,
            'published_at' => null,
            'is_pinned' => false,
            'status' => 'draft',
            'attachments' => [
                [
                    'name' => '機密草案.pdf',
                    'path' => $fakePath,
                    'size' => 54321,
                    'mime' => 'application/pdf',
                ],
            ],
        ]);

        // 一般同仁不可調閱草稿公告（404 防洩露）
        $showResponse = $this->actingAs($this->employee)->get(route('announcements.show', $draftAnnouncement->id));
        $showResponse->assertNotFound();

        // 一般同仁不可下載草稿公告附件（404 防洩露）
        $downloadResponse = $this->actingAs($this->employee)->get(
            route('announcements.attachments.download', ['announcement' => $draftAnnouncement->id, 'index' => 0])
        );
        $downloadResponse->assertNotFound();

        // 管理員可調閱與下載
        $adminShow = $this->actingAs($this->admin)->get(route('announcements.show', $draftAnnouncement->id));
        $adminShow->assertOk();

        $adminDownload = $this->actingAs($this->admin)->get(
            route('announcements.attachments.download', ['announcement' => $draftAnnouncement->id, 'index' => 0])
        );
        $adminDownload->assertOk();
        $this->assertStringContainsString('attachment', $adminDownload->headers->get('content-disposition'));
        $this->assertStringContainsString(rawurlencode('機密草案.pdf'), $adminDownload->headers->get('content-disposition'));
    }

    public function test_admin_can_update_announcement_and_append_attachments(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $initialPath = 'announcements/attachments/initial.pdf';
        Storage::disk('public')->put($initialPath, 'Initial file content');

        $announcement = Announcement::create([
            'title' => '初版活動資訊',
            'category' => 'activity',
            'priority' => 'normal',
            'content' => '初版內文',
            'author_id' => $this->manager->id,
            'published_at' => now(),
            'is_pinned' => false,
            'status' => 'published',
            'attachments' => [
                [
                    'name' => '初版檔案.pdf',
                    'path' => $initialPath,
                    'size' => 1024,
                    'mime' => 'application/pdf',
                ],
            ],
        ]);

        $newFile = UploadedFile::fake()->create('更新行程表.xlsx', 500, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->actingAs($this->manager)->put(route('announcements.update', $announcement->id), [
            'title' => '初版活動資訊 (已更新日程)',
            'category' => 'activity',
            'priority' => 'high',
            'content' => '日程更新版內文',
            'is_pinned' => true,
            'status' => 'published',
            'attachments' => [$newFile],
        ]);

        $response->assertRedirect(route('announcements.show', $announcement->id));
        $response->assertSessionHas('success');

        $announcement->refresh();
        $this->assertEquals('初版活動資訊 (已更新日程)', $announcement->title);
        $this->assertTrue($announcement->is_pinned);
        $this->assertCount(2, $announcement->attachments);
        $this->assertEquals('初版檔案.pdf', $announcement->attachments[0]['name']);
        $this->assertEquals('更新行程表.xlsx', $announcement->attachments[1]['name']);
        Storage::disk('local')->assertExists($announcement->attachments[1]['path']);
    }

    public function test_admin_can_delete_announcement_and_storage_files_are_cleaned(): void
    {
        Storage::fake('public');

        $filePath = 'announcements/attachments/to_be_deleted.pdf';
        Storage::disk('public')->put($filePath, 'Will be removed');
        Storage::disk('public')->assertExists($filePath);

        $announcement = Announcement::create([
            'title' => '即將下架之過期公告',
            'category' => 'general',
            'priority' => 'low',
            'content' => '即將刪除',
            'author_id' => $this->admin->id,
            'published_at' => now(),
            'is_pinned' => false,
            'status' => 'published',
            'attachments' => [
                [
                    'name' => '即將刪除檔案.pdf',
                    'path' => $filePath,
                    'size' => 2048,
                    'mime' => 'application/pdf',
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->delete(route('announcements.destroy', $announcement->id));

        $response->assertRedirect(route('announcements.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        Storage::disk('public')->assertMissing($filePath);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'delete_announcement',
        ]);
    }

    public function test_urgent_announcement_triggers_webhook(): void
    {
        Storage::fake('public');
        Http::fake();

        Webhook::create([
            'name' => 'Slack 公告推播機器人',
            'url' => 'https://example.com/webhook/slack-announcement',
            'events' => ['announcement.published'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('announcements.store'), [
            'title' => '緊急防颱應變指引',
            'category' => 'company',
            'priority' => 'urgent',
            'content' => '依據人事行政總處公告，明日全體居家辦公。',
            'is_pinned' => true,
            'status' => 'published',
        ]);

        $response->assertRedirect(route('announcements.index'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook/slack-announcement' &&
                   $request['event'] === 'announcement.published' &&
                   $request['data']['title'] === '緊急防颱應變指引';
        });
    }
}
