<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingRoomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private User $otherEmployee;
    private MeetingRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $dept = Department::create(['name' => '資訊處', 'code' => 'IT']);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'department_id' => $dept->id,
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $dept->id,
        ]);

        $this->otherEmployee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $dept->id,
        ]);

        $this->room = MeetingRoom::create([
            'name' => '101 創新會議室',
            'location' => 'A棟 1F',
            'capacity' => 10,
            'equipment' => ['投影機', '視訊會議設備'],
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_can_view_meeting_rooms_page(): void
    {
        $response = $this->actingAs($this->employee)->get('/meeting-rooms');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('MeetingRooms/Index')
            ->has('rooms')
            ->has('selectedDate')
        );
    }

    public function test_user_can_book_meeting_room_successfully(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $startTime = "{$tomorrow} 10:00:00";
        $endTime = "{$tomorrow} 11:30:00";

        $response = $this->actingAs($this->employee)->post('/meeting-rooms/bookings', [
            'meeting_room_id' => $this->room->id,
            'title' => '全端架構技術評審會議',
            'description' => '討論系統擴展性與測試',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'attendees_count' => 5,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('room_bookings', [
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->employee->id,
            'title' => '全端架構技術評審會議',
            'status' => 'confirmed',
            'attendees_count' => 5,
        ]);
    }

    public function test_booking_conflict_is_prevented(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        // 既有預約：10:00 ~ 12:00
        RoomBooking::create([
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->employee->id,
            'title' => '既有預約會議',
            'start_time' => "{$tomorrow} 10:00:00",
            'end_time' => "{$tomorrow} 12:00:00",
            'attendees_count' => 3,
            'status' => 'confirmed',
        ]);

        // 其他同仁嘗試預約重疊時段：11:00 ~ 13:00 (重疊一小時)
        $response = $this->actingAs($this->otherEmployee)->post('/meeting-rooms/bookings', [
            'meeting_room_id' => $this->room->id,
            'title' => '嘗試重複預約',
            'start_time' => "{$tomorrow} 11:00:00",
            'end_time' => "{$tomorrow} 13:00:00",
            'attendees_count' => 4,
        ]);

        // 必須驗證失敗，阻擋衝突
        $response->assertSessionHasErrors(['start_time']);

        $this->assertDatabaseMissing('room_bookings', [
            'title' => '嘗試重複預約',
        ]);
    }

    public function test_cannot_book_inactive_meeting_room(): void
    {
        $this->room->update(['is_active' => false]);
        $tomorrow = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->employee)->post('/meeting-rooms/bookings', [
            'meeting_room_id' => $this->room->id,
            'title' => '維護中會議室借用',
            'start_time' => "{$tomorrow} 14:00:00",
            'end_time' => "{$tomorrow} 15:00:00",
        ]);

        $response->assertSessionHasErrors(['meeting_room_id']);
    }

    public function test_creator_can_cancel_their_booking(): void
    {
        $booking = RoomBooking::create([
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->employee->id,
            'title' => '自辦討論會',
            'start_time' => Carbon::tomorrow()->setTime(9, 0),
            'end_time' => Carbon::tomorrow()->setTime(10, 0),
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->employee)->post("/meeting-rooms/bookings/{$booking->id}/cancel", [
            'reason' => '臨時臨時更改行程',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('room_bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'cancel_reason' => '臨時臨時更改行程',
        ]);
    }

    public function test_other_user_cannot_cancel_others_booking(): void
    {
        $booking = RoomBooking::create([
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->employee->id,
            'title' => '陳同仁的私有預約',
            'start_time' => Carbon::tomorrow()->setTime(9, 0),
            'end_time' => Carbon::tomorrow()->setTime(10, 0),
            'status' => 'confirmed',
        ]);

        // 另一位同仁嘗試取消
        $response = $this->actingAs($this->otherEmployee)->post("/meeting-rooms/bookings/{$booking->id}/cancel");

        $response->assertStatus(403);
        $this->assertEquals('confirmed', $booking->fresh()->status);
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $booking = RoomBooking::create([
            'meeting_room_id' => $this->room->id,
            'user_id' => $this->employee->id,
            'title' => '陳同仁的預約',
            'start_time' => Carbon::tomorrow()->setTime(9, 0),
            'end_time' => Carbon::tomorrow()->setTime(10, 0),
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->admin)->post("/meeting-rooms/bookings/{$booking->id}/cancel", [
            'reason' => '管理員系統協調調度',
        ]);

        $response->assertRedirect();
        $this->assertEquals('cancelled', $booking->fresh()->status);
    }

    public function test_admin_can_create_new_meeting_room(): void
    {
        $response = $this->actingAs($this->admin)->post('/meeting-rooms', [
            'name' => '501 雲端研發中心',
            'location' => '台北總部 C棟 5F',
            'capacity' => 15,
            'equipment' => ['電子白板', '視訊會議設備'],
            'description' => '高階研發專用',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_rooms', [
            'name' => '501 雲端研發中心',
            'capacity' => 15,
        ]);
    }

    public function test_regular_employee_cannot_create_meeting_room(): void
    {
        $response = $this->actingAs($this->employee)->post('/meeting-rooms', [
            'name' => '未經授權會議室',
            'location' => '1F',
            'capacity' => 5,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_book_with_attendees_exceeding_capacity(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        // 會議室上限為 10 人，填寫 30 人
        $response = $this->actingAs($this->employee)->post('/meeting-rooms/bookings', [
            'meeting_room_id' => $this->room->id,
            'title' => '超額人數會議',
            'start_time' => "{$tomorrow} 15:00:00",
            'end_time' => "{$tomorrow} 16:00:00",
            'attendees_count' => 30,
        ]);

        $response->assertSessionHasErrors(['attendees_count']);
    }
}
