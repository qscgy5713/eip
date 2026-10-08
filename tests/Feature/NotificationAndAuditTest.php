<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\MeetingRoom;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    public function test_authenticated_user_can_view_notifications(): void
    {
        $user = User::where('email', 'employee@eip.local')->first();

        // 建立一筆測試通知
        $user->notify(new EipSystemNotification(
            title: '測試通知',
            message: '這是一則測試通知內容',
            type: 'system'
        ));

        $response = $this->actingAs($user)->get('/notifications');
        $response->assertStatus(200);
    }

    public function test_user_can_mark_notification_as_read_and_read_all(): void
    {
        $user = User::where('email', 'employee@eip.local')->first();
        $user->notifications()->delete(); // 清空種子資料以利精確驗證

        $user->notify(new EipSystemNotification(title: '通知一', message: '內文一'));
        $user->notify(new EipSystemNotification(title: '通知二', message: '內文二'));

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $firstNotification = $user->unreadNotifications()->first();

        // 標記單則為已讀
        $response = $this->actingAs($user)->post("/notifications/{$firstNotification->id}/read");
        $response->assertRedirect();
        $this->assertEquals(1, $user->fresh()->unreadNotifications()->count());

        // 全部標記為已讀
        $responseAll = $this->actingAs($user)->post('/notifications/read-all');
        $responseAll->assertRedirect();
        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_notifications_and_audit_logs_created_on_form_submission_and_approval(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();
        $manager = User::where('email', 'manager@eip.local')->first();
        $form = Form::where('code', 'LEAVE')->first();

        // 1. 同仁送單
        $response = $this->actingAs($employee)->post("/forms/create/{$form->id}", [
            'title' => '端午連假調休申請',
            'data' => [
                'leave_type' => '特休假',
                'start_date' => '2026-06-18',
                'end_date' => '2026-06-19',
                'days' => 2,
                'reason' => '家族返鄉團聚',
            ],
        ]);

        $response->assertRedirect();

        // 驗證主管收到待簽核通知
        $this->assertTrue($manager->notifications()->where('data->type', 'form_approval')->exists());

        // 驗證審計日誌已記錄
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $employee->id,
            'action' => 'submit_form_request',
        ]);

        $newRequest = FormRequest::where('user_id', $employee->id)->where('title', '端午連假調休申請')->first();

        // 2. 主管審批
        $approveResponse = $this->actingAs($manager)->post("/forms/requests/{$newRequest->id}/action", [
            'status' => 'approved',
            'comment' => '准假，祝佳節愉快！',
        ]);

        $approveResponse->assertRedirect();

        // 驗證原同仁收到核准通知
        $this->assertTrue($employee->notifications()->where('data->type', 'form_approval')->exists());

        // 驗證審批審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'approve_form_request',
        ]);
    }

    public function test_booking_meeting_room_creates_notification_and_audit_log(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();
        $room = MeetingRoom::first();

        $tomorrow = now()->addDay()->format('Y-m-d');
        $startTime = "{$tomorrow} 10:00:00";
        $endTime = "{$tomorrow} 11:00:00";

        $response = $this->actingAs($employee)->post('/meeting-rooms/bookings', [
            'meeting_room_id' => $room->id,
            'title' => '每週團隊 Sprint 規劃會議',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'attendees_count' => 5,
        ]);

        $response->assertRedirect();

        // 驗證借用同仁收到確認通知
        $this->assertTrue($employee->notifications()->where('data->type', 'meeting_room')->exists());

        // 驗證審計日誌記錄
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $employee->id,
            'action' => 'book_meeting_room',
        ]);
    }

    public function test_admin_can_access_audit_logs(): void
    {
        $admin = User::where('email', 'admin@eip.local')->first();

        // 產生一筆審計日誌
        AuditLog::log('test_action', '測試審計動作記錄', null, ['foo' => 'bar'], $admin);

        $response = $this->actingAs($admin)->get('/audit-logs');
        $response->assertStatus(200);
    }

    public function test_regular_employee_cannot_access_audit_logs(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();

        $response = $this->actingAs($employee)->get('/audit-logs');
        $response->assertStatus(403);
    }
}
