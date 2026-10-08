<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $this->department = Department::first();
        $this->user = User::factory()->create([
            'role' => 'employee',
            'department_id' => $this->department->id,
        ]);
    }

    public function test_authenticated_user_can_access_calendar_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('calendar.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Index')
            ->has('events')
            ->has('departments')
            ->has('filters')
        );
    }

    public function test_calendar_aggregates_room_bookings(): void
    {
        $room = MeetingRoom::create([
            'name' => '101 第一會議室',
            'location' => '1F 研發大樓',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $booking = RoomBooking::create([
            'meeting_room_id' => $room->id,
            'user_id' => $this->user->id,
            'title' => '全景行事曆架構討論會',
            'start_time' => now()->startOfMonth()->addDays(5)->setHour(14)->setMinute(0),
            'end_time' => now()->startOfMonth()->addDays(5)->setHour(15)->setMinute(30),
            'status' => 'confirmed',
            'attendees_count' => 6,
        ]);

        $response = $this->actingAs($this->user)->get(route('calendar.index', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Index')
            ->where('events', fn ($events) => collect($events)->contains('raw_id', $booking->id)
                && collect($events)->contains('type', 'meeting')
            )
        );
    }

    public function test_calendar_aggregates_approved_leave_form_requests(): void
    {
        $leaveForm = Form::where('name', 'like', '%假%')->first()
            ?? Form::create([
                'name' => '特休假申請單',
                'description' => '員工休假',
                'fields_schema' => [],
            ]);

        $targetDate = now()->startOfMonth()->addDays(10)->format('Y-m-d');

        $formRequest = FormRequest::create([
            'form_id' => $leaveForm->id,
            'user_id' => $this->user->id,
            'request_no' => 'REQ-TEST-LEAVE-01',
            'title' => '測試休假',
            'status' => 'approved',
            'current_step' => 2,
            'data' => [
                'start_date' => $targetDate,
                'end_date' => $targetDate,
                'leave_type' => '特休假',
                'reason' => '行事曆測試事由',
                'days' => 1,
            ],
        ]);

        $response = $this->actingAs($this->user)->get(route('calendar.index', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Index')
            ->where('events', fn ($events) => collect($events)->contains('raw_id', $formRequest->id)
                && collect($events)->contains('type', 'leave')
            )
        );
    }

    public function test_calendar_aggregates_published_announcements(): void
    {
        $announcement = Announcement::create([
            'author_id' => $this->user->id,
            'title' => '公司年度運動會公告',
            'content' => '詳情請見全景行事曆說明。',
            'category' => 'company',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now()->startOfMonth()->addDays(12),
        ]);

        $response = $this->actingAs($this->user)->get(route('calendar.index', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Index')
            ->where('events', fn ($events) => collect($events)->contains('raw_id', $announcement->id)
                && collect($events)->contains('type', 'announcement')
            )
        );
    }

    public function test_calendar_can_filter_by_type_and_department(): void
    {
        $room = MeetingRoom::create([
            'name' => '102 腦力激盪室',
            'location' => '2F 創意大樓',
            'capacity' => 6,
            'is_active' => true,
        ]);

        $booking = RoomBooking::create([
            'meeting_room_id' => $room->id,
            'user_id' => $this->user->id,
            'title' => '行事曆篩選測試會議',
            'start_time' => now()->startOfMonth()->addDays(8)->setHour(10)->setMinute(0),
            'end_time' => now()->startOfMonth()->addDays(8)->setHour(11)->setMinute(0),
            'status' => 'confirmed',
            'attendees_count' => 3,
        ]);

        // 僅篩選會議
        $responseMeetingOnly = $this->actingAs($this->user)->get(route('calendar.index', [
            'month' => now()->format('Y-m'),
            'type' => 'meeting',
        ]));

        $responseMeetingOnly->assertStatus(200);
        $responseMeetingOnly->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Index')
            ->where('events', fn ($events) => collect($events)->every(fn ($e) => $e['type'] === 'meeting'))
        );
    }
}
