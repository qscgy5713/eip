<?php

namespace Tests\Feature;

use App\Models\MeetingRoom;
use App\Models\User;
use App\Models\VisitorReservation;
use App\Notifications\EipSystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VisitorReservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected MeetingRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->room = MeetingRoom::create([
            'name' => '101 貴賓會議室',
            'capacity' => 8,
            'location' => '1F 大廳旁',
            'is_active' => true,
        ]);
    }

    public function test_user_can_view_visitor_reservations_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('visitor-reservations.index'));
        $response->assertStatus(200);
    }

    public function test_user_can_create_visitor_reservation(): void
    {
        $payload = [
            'visitor_name' => '黃董事長',
            'visitor_company' => '大同盟科技',
            'visitor_email' => 'huang@partner.com',
            'visitor_phone' => '0988-123-456',
            'visit_date' => now()->toDateString(),
            'expected_arrival_time' => '14:30',
            'purpose' => 'business',
            'meeting_room_id' => $this->room->id,
            'visitor_count' => 3,
            'notes' => '需準備貴賓高山茶與投影轉接頭',
        ];

        $response = $this->actingAs($this->user)->post(route('visitor-reservations.store'), $payload);

        $reservation = VisitorReservation::first();
        $this->assertNotNull($reservation);
        $this->assertEquals('黃董事長', $reservation->visitor_name);
        $this->assertStringStartsWith('VR-', $reservation->reservation_code);
        $this->assertEquals($this->user->id, $reservation->host_id);
        $this->assertEquals('expected', $reservation->status);

        $response->assertRedirect(route('visitor-reservations.show', $reservation));
    }

    public function test_user_can_view_reservation_show(): void
    {
        $reservation = VisitorReservation::create([
            'reservation_code' => VisitorReservation::generateCode(),
            'host_id' => $this->user->id,
            'visitor_name' => '李經理',
            'visitor_company' => '協力廠商',
            'visit_date' => now()->toDateString(),
            'purpose' => 'vendor',
            'visitor_count' => 1,
            'status' => 'expected',
        ]);

        $response = $this->actingAs($this->user)->get(route('visitor-reservations.show', $reservation));
        $response->assertStatus(200);
    }

    public function test_front_desk_check_in_notifies_host(): void
    {
        Notification::fake();

        $reservation = VisitorReservation::create([
            'reservation_code' => VisitorReservation::generateCode(),
            'host_id' => $this->user->id,
            'visitor_name' => '張總經理',
            'visitor_company' => '投資夥伴集團',
            'visit_date' => now()->toDateString(),
            'purpose' => 'business',
            'visitor_count' => 2,
            'status' => 'expected',
        ]);

        $response = $this->actingAs($this->admin)->post(route('visitor-reservations.check-in', $reservation), [
            'badge_number' => 'CARD-99',
        ]);

        $response->assertSessionHas('success');

        $reservation->refresh();
        $this->assertEquals('checked_in', $reservation->status);
        $this->assertEquals('CARD-99', $reservation->badge_number);
        $this->assertNotNull($reservation->actual_check_in_at);

        Notification::assertSentTo(
            $this->user,
            EipSystemNotification::class,
            function ($notification) {
                return str_contains($notification->title, '訪客已簽到')
                    && str_contains($notification->message, '張總經理');
            }
        );
    }

    public function test_front_desk_can_check_out_visitor(): void
    {
        $reservation = VisitorReservation::create([
            'reservation_code' => VisitorReservation::generateCode(),
            'host_id' => $this->user->id,
            'visitor_name' => '趙顧問',
            'visit_date' => now()->toDateString(),
            'purpose' => 'audit',
            'visitor_count' => 1,
            'status' => 'checked_in',
            'actual_check_in_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->user)->post(route('visitor-reservations.check-out', $reservation));
        $response->assertSessionHas('success');

        $reservation->refresh();
        $this->assertEquals('checked_out', $reservation->status);
        $this->assertNotNull($reservation->actual_check_out_at);
    }

    public function test_host_or_admin_can_cancel_reservation(): void
    {
        $reservation = VisitorReservation::create([
            'reservation_code' => VisitorReservation::generateCode(),
            'host_id' => $this->user->id,
            'visitor_name' => '面試者林小姐',
            'visit_date' => now()->addDay()->toDateString(),
            'purpose' => 'interview',
            'visitor_count' => 1,
            'status' => 'expected',
        ]);

        $otherUser = User::factory()->create(['role' => 'employee']);

        // 非關係人無法取消 (403)
        $this->actingAs($otherUser)
            ->post(route('visitor-reservations.cancel', $reservation))
            ->assertStatus(403);

        // 接待本人可以取消
        $response = $this->actingAs($this->user)->post(route('visitor-reservations.cancel', $reservation));
        $response->assertSessionHas('success');

        $reservation->refresh();
        $this->assertEquals('cancelled', $reservation->status);
    }

    public function test_visitor_logs_export_as_csv(): void
    {
        VisitorReservation::create([
            'reservation_code' => VisitorReservation::generateCode(),
            'host_id' => $this->user->id,
            'visitor_name' => '測試稽核員',
            'visitor_company' => 'ISO認證中心',
            'visit_date' => now()->toDateString(),
            'purpose' => 'audit',
            'visitor_count' => 1,
            'status' => 'checked_in',
        ]);

        $response = $this->actingAs($this->user)->get(route('visitor-reservations.export'));
        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function test_global_search_finds_visitor_reservation(): void
    {
        $reservation = VisitorReservation::create([
            'reservation_code' => 'VR-9999-TEST',
            'host_id' => $this->user->id,
            'visitor_name' => '獨特貴賓王小明',
            'visitor_company' => '特殊科技有限公司',
            'visit_date' => now()->toDateString(),
            'purpose' => 'business',
            'visitor_count' => 1,
            'status' => 'expected',
        ]);

        $response = $this->actingAs($this->user)->get(route('global-search', ['q' => '王小明']));
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('visitor_reservations', $data['results']);
        $this->assertEquals('VR-9999-TEST', $data['results']['visitor_reservations']['items'][0]['subtitle'] ? 'VR-9999-TEST' : '');
    }
}
