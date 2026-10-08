<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Document;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUpdateAndIcsExportTest extends TestCase
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

        $this->dept = Department::create(['name' => '資訊工程部', 'code' => 'IT']);

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

    public function test_admin_can_update_document_metadata_and_restricted_roles(): void
    {
        $doc = Document::create([
            'title' => '伺服器維運標準指南',
            'category' => 'tech',
            'department_id' => $this->dept->id,
            'description' => '舊版備註',
            'restricted_roles' => null,
            'current_version' => 1,
            'uploader_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('documents.update', $doc->id), [
            'title' => '伺服器維運標準指南 (機密修訂版)',
            'category' => 'policy',
            'department_id' => $this->dept->id,
            'description' => '僅限管理人員檢閱',
            'restricted_roles' => ['admin', 'manager'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $doc->refresh();
        $this->assertEquals('伺服器維運標準指南 (機密修訂版)', $doc->title);
        $this->assertEquals('policy', $doc->category);
        $this->assertEquals('僅限管理人員檢閱', $doc->description);
        $this->assertEquals(['admin', 'manager'], $doc->restricted_roles);

        // 驗證審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update_document',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_document_author_can_update_own_document(): void
    {
        $doc = Document::create([
            'title' => '員工自建交接筆記',
            'category' => 'training',
            'department_id' => null,
            'description' => '草稿',
            'current_version' => 1,
            'uploader_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employee)->put(route('documents.update', $doc->id), [
            'title' => '員工自建交接筆記 (完稿)',
            'category' => 'training',
            'department_id' => $this->dept->id,
            'description' => '已完成交接',
            'restricted_roles' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $doc->refresh();
        $this->assertEquals('員工自建交接筆記 (完稿)', $doc->title);
        $this->assertNull($doc->restricted_roles);
    }

    public function test_unauthorized_user_cannot_update_others_document(): void
    {
        $otherEmployee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $this->dept->id,
        ]);

        $doc = Document::create([
            'title' => '重要規章',
            'category' => 'policy',
            'current_version' => 1,
            'uploader_id' => $this->manager->id,
        ]);

        $response = $this->actingAs($otherEmployee)->put(route('documents.update', $doc->id), [
            'title' => '惡意修改標題',
            'category' => 'policy',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_document_validation(): void
    {
        $doc = Document::create([
            'title' => '原始文件',
            'category' => 'policy',
            'current_version' => 1,
            'uploader_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('documents.update', $doc->id), [
            'title' => '',
            'category' => 'invalid_cat',
        ]);

        $response->assertSessionHasErrors(['title', 'category']);
    }

    public function test_single_meeting_booking_export_ics(): void
    {
        $room = MeetingRoom::create([
            'name' => '101 戰情視訊會議室',
            'capacity' => 12,
            'location' => '台北總部 10 樓',
            'equipment' => ['視訊鏡頭', '麥克風'],
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-10-15 10:00:00');
        $end = Carbon::parse('2026-10-15 11:30:00');

        $booking = RoomBooking::create([
            'meeting_room_id' => $room->id,
            'user_id' => $this->manager->id,
            'title' => '2026 Q4 全球產品策略規劃會',
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'confirmed',
            'attendees_count' => 5,
            'description' => '討論 Q4 產品發布里程碑',
            'equipment_needed' => ['視訊鏡頭'],
        ]);

        $booking->attendees()->attach([$this->employee->id, $this->admin->id]);

        $response = $this->actingAs($this->employee)
            ->get(route('meeting-rooms.bookings.export-ics', $booking->id));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('meeting-' . $booking->id . '.ics', $response->headers->get('content-disposition'));

        $content = $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('VERSION:2.0', $content);
        $this->assertStringContainsString('PRODID:-//EIP Portal//Meeting Rooms//TW', $content);
        $this->assertStringContainsString('BEGIN:VEVENT', $content);
        $this->assertStringContainsString('SUMMARY:101 戰情視訊會議室 · 2026 Q4 全球產品策略規劃會', $content);
        $this->assertStringContainsString('LOCATION:台北總部 10 樓 (101 戰情視訊會議室)', $content);
        $this->assertStringContainsString('ORGANIZER;CN=' . $this->manager->name . ':mailto:' . $this->manager->email, $content);
        $this->assertStringContainsString('ATTENDEE;ROLE=REQ-PARTICIPANT;CN=' . $this->employee->name . ':mailto:' . $this->employee->email, $content);
        $this->assertStringContainsString('END:VEVENT', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }

    public function test_full_calendar_month_export_ics(): void
    {
        $room = MeetingRoom::create([
            'name' => '201 企劃會議室',
            'capacity' => 8,
            'location' => '2F',
            'is_active' => true,
        ]);

        // 1. 會議
        RoomBooking::create([
            'meeting_room_id' => $room->id,
            'user_id' => $this->manager->id,
            'title' => '雙週敏捷衝刺檢視',
            'start_time' => '2026-10-12 14:00:00',
            'end_time' => '2026-10-12 15:00:00',
            'status' => 'confirmed',
            'attendees_count' => 3,
        ]);

        // 2. 請假假單
        $leaveForm = Form::create([
            'name' => '特休請假單',
            'code' => 'FORM_LEAVE',
            'description' => '員工請假申請',
            'fields_schema' => [],
        ]);

        FormRequest::create([
            'form_id' => $leaveForm->id,
            'user_id' => $this->employee->id,
            'request_no' => 'REQ-LEAVE-202610-001',
            'title' => '特休請假申請',
            'status' => 'approved',
            'current_step' => 2,
            'data' => [
                'leave_type' => '特休',
                'start_date' => '2026-10-20',
                'end_date' => '2026-10-21',
                'days' => 2,
                'reason' => '家族聚會',
            ],
        ]);

        // 3. 公告
        Announcement::create([
            'title' => '2026 國慶節連續假期放假通知',
            'content' => '國慶連假依人事行政局規定放假。',
            'category' => 'company',
            'is_pinned' => true,
            'status' => 'published',
            'published_at' => '2026-10-05 09:00:00',
            'author_id' => $this->admin->id,
        ]);

        // 請求 2026-10 的 ICS 行事曆匯出
        $response = $this->actingAs($this->employee)
            ->get(route('calendar.export-ics', ['month' => '2026-10']));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('eip-calendar-2026-10.ics', $response->headers->get('content-disposition'));

        $content = $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('SUMMARY:201 企劃會議室 · 雙週敏捷衝刺檢視', $content);
        $this->assertStringContainsString('SUMMARY:' . $this->employee->name . ' (特休)', $content);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261020', $content);
        $this->assertStringContainsString('SUMMARY:[公告] 2026 國慶節連續假期放假通知', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }

    public function test_calendar_ics_export_excludes_unpublished_draft_announcements(): void
    {
        // 建立草稿未發布的公告
        Announcement::create([
            'title' => '2027 主管級營運方針密件 (草稿)',
            'content' => '草稿內容，尚未審核通過。',
            'category' => 'company',
            'status' => 'draft',
            'is_pinned' => false,
            'published_at' => null,
            'author_id' => $this->admin->id,
        ]);

        // 已發布的公告
        Announcement::create([
            'title' => '2026 全體同仁大會日程',
            'content' => '公開大會。',
            'category' => 'company',
            'status' => 'published',
            'is_pinned' => false,
            'published_at' => '2026-10-18 10:00:00',
            'author_id' => $this->admin->id,
        ]);

        // 匯出日曆 -> 不得包含草稿公告，必須包含已發布公告
        $response = $this->actingAs($this->employee)
            ->get(route('calendar.export-ics', ['month' => '2026-10']));

        $content = $response->getContent();
        $this->assertStringNotContainsString('2027 主管級營運方針密件 (草稿)', $content);
        $this->assertStringContainsString('2026 全體同仁大會日程', $content);
    }
}
