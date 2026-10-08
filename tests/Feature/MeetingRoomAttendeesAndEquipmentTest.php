<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MeetingRoomAttendeesAndEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected User $invitee1;
    protected User $invitee2;
    protected MeetingRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $department = Department::first();

        $this->host = User::factory()->create([
            'name' => '會議發起人主管',
            'role' => 'manager',
            'department_id' => $department->id,
        ]);

        $this->invitee1 = User::factory()->create([
            'name' => '受邀同仁甲',
            'role' => 'employee',
            'department_id' => $department->id,
        ]);

        $this->invitee2 = User::factory()->create([
            'name' => '受邀同仁乙',
            'role' => 'employee',
            'department_id' => $department->id,
        ]);

        $this->room = MeetingRoom::create([
            'name' => '301 敏捷創新會議室',
            'location' => '總部 3F',
            'capacity' => 6,
            'equipment' => ['投影機', '視訊會議設備', '電子白板'],
            'is_active' => true,
        ]);
    }

    public function test_user_can_book_room_with_attendees_and_equipment(): void
    {
        Notification::fake();

        $startTime = Carbon::tomorrow()->setTime(14, 0);
        $endTime = Carbon::tomorrow()->setTime(15, 30);

        $response = $this->actingAs($this->host)->post(route('meeting-rooms.bookings.store'), [
            'meeting_room_id' => $this->room->id,
            'title' => '2026年度產品敏捷開發同步會議',
            'description' => '請受邀同仁攜帶個人電腦出席',
            'start_time' => $startTime->toDateTimeLocalString(),
            'end_time' => $endTime->toDateTimeLocalString(),
            'attendees_count' => 3,
            'attendee_ids' => [$this->invitee1->id, $this->invitee2->id],
            'equipment_needed' => ['投影機', '視訊會議設備'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 資料庫記錄確認
        $this->assertDatabaseHas('room_bookings', [
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->host->id,
            'title' => '2026年度產品敏捷開發同步會議',
            'status' => 'confirmed',
        ]);

        $booking = RoomBooking::where('title', '2026年度產品敏捷開發同步會議')->first();
        $this->assertNotNull($booking);
        $this->assertEquals(['投影機', '視訊會議設備'], $booking->equipment_needed);
        $this->assertEquals(3, $booking->attendees_count);

        // 樞紐表與會同仁關聯確認
        $this->assertCount(2, $booking->attendees);
        $this->assertTrue($booking->attendees->contains($this->invitee1));
        $this->assertTrue($booking->attendees->contains($this->invitee2));

        // 受邀同仁通知確認
        Notification::assertSentTo(
            $this->invitee1,
            EipSystemNotification::class,
            function ($notification) {
                return str_contains($notification->title, '會議邀請') &&
                       str_contains($notification->message, '301 敏捷創新會議室');
            }
        );

        Notification::assertSentTo(
            $this->invitee2,
            EipSystemNotification::class,
            function ($notification) {
                return str_contains($notification->title, '會議邀請');
            }
        );
    }

    public function test_cancelling_booking_notifies_all_invited_attendees(): void
    {
        Notification::fake();

        $booking = RoomBooking::create([
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->host->id,
            'title' => '即將取消的測試會議',
            'start_time' => Carbon::tomorrow()->setTime(10, 0),
            'end_time' => Carbon::tomorrow()->setTime(11, 0),
            'attendees_count' => 3,
            'status' => 'confirmed',
        ]);

        $booking->attendees()->sync([$this->invitee1->id, $this->invitee2->id]);

        $response = $this->actingAs($this->host)->post(route('meeting-rooms.bookings.cancel', $booking->id), [
            'reason' => '客戶臨時變更行程',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertEquals('cancelled', $booking->status);
        $this->assertEquals('客戶臨時變更行程', $booking->cancel_reason);

        // 受邀同仁連鎖收到會議取消通知
        Notification::assertSentTo(
            $this->invitee1,
            EipSystemNotification::class,
            function ($notification) {
                return str_contains($notification->title, '會議取消通知') &&
                       str_contains($notification->message, '客戶臨時變更行程');
            }
        );

        Notification::assertSentTo(
            $this->invitee2,
            EipSystemNotification::class,
            function ($notification) {
                return str_contains($notification->title, '會議取消通知');
            }
        );
    }

    public function test_my_bookings_includes_both_hosted_and_invited_meetings(): void
    {
        $booking = RoomBooking::create([
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->host->id,
            'title' => '主管主辦的專案會議',
            'start_time' => Carbon::tomorrow()->setTime(9, 0),
            'end_time' => Carbon::tomorrow()->setTime(10, 0),
            'attendees_count' => 2,
            'status' => 'confirmed',
        ]);
        $booking->attendees()->sync([$this->invitee1->id]);

        // 受邀人調閱 meeting-rooms.index
        $response = $this->actingAs($this->invitee1)->get(route('meeting-rooms.index'));
        $response->assertOk();

        // 驗證 Inertia prop myBookings 包含此會議
        $response->assertInertia(fn ($page) =>
            $page->component('MeetingRooms/Index')
                ->has('myBookings', 1)
                ->where('myBookings.0.title', '主管主辦的專案會議')
        );
    }

    public function test_dashboard_upcoming_bookings_includes_invited_meetings(): void
    {
        $booking = RoomBooking::create([
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->host->id,
            'title' => '跨團隊產品評審會議',
            'start_time' => Carbon::tomorrow()->setTime(16, 0),
            'end_time' => Carbon::tomorrow()->setTime(17, 0),
            'attendees_count' => 2,
            'status' => 'confirmed',
        ]);
        $booking->attendees()->sync([$this->invitee1->id]);

        // 受邀人登入首頁工作台
        $response = $this->actingAs($this->invitee1)->get(route('dashboard'));
        $response->assertOk();

        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard')
                ->has('myUpcomingBookings', 1)
                ->where('myUpcomingBookings.0.title', '跨團隊產品評審會議')
                ->where('myUpcomingBookings.0.is_host', false)
        );
    }

    public function test_calendar_aggregates_meeting_attendees_and_equipment(): void
    {
        $booking = RoomBooking::create([
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->host->id,
            'title' => '年度營運戰略研討',
            'start_time' => Carbon::tomorrow()->setTime(13, 0),
            'end_time' => Carbon::tomorrow()->setTime(15, 0),
            'attendees_count' => 2,
            'equipment_needed' => ['投影機'],
            'status' => 'confirmed',
        ]);
        $booking->attendees()->sync([$this->invitee1->id]);

        $response = $this->actingAs($this->invitee1)->get(route('calendar.index', [
            'month' => Carbon::tomorrow()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Calendar/Index')
                ->has('events')
        );
    }

    public function test_booking_with_attendees_exceeding_capacity_is_prevented(): void
    {
        // 會議室上限 6 人，嘗試邀請 7 人
        $extraUsers = User::factory()->count(7)->create();

        $response = $this->actingAs($this->host)->post(route('meeting-rooms.bookings.store'), [
            'meeting_room_id' => $this->room->id,
            'title' => '超員的會議測試',
            'start_time' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeLocalString(),
            'end_time' => Carbon::tomorrow()->setTime(11, 0)->toDateTimeLocalString(),
            'attendee_ids' => $extraUsers->pluck('id')->toArray(),
        ]);

        $response->assertSessionHasErrors('attendees_count');
    }
}
