<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\MeetingRoom;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    public function test_admin_can_view_webhooks_index(): void
    {
        $admin = User::where('email', 'admin@eip.local')->first();

        $response = $this->actingAs($admin)->get('/webhooks');
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_webhooks(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();

        $response = $this->actingAs($employee)->get('/webhooks');
        $response->assertStatus(403);
    }

    public function test_admin_can_create_webhook(): void
    {
        $admin = User::where('email', 'admin@eip.local')->first();

        $response = $this->actingAs($admin)->post('/webhooks', [
            'name' => 'Slack 通報頻道',
            'url' => 'https://hooks.slack.com/services/TEST/TOKEN',
            'events' => ['form.submitted', 'form.approved'],
            'secret' => 'supersecretkey',
        ]);

        $response->assertRedirect('/webhooks');
        $this->assertDatabaseHas('webhooks', [
            'name' => 'Slack 通報頻道',
            'url' => 'https://hooks.slack.com/services/TEST/TOKEN',
            'is_active' => true,
        ]);

        $webhook = Webhook::where('name', 'Slack 通報頻道')->first();
        $this->assertEquals(['form.submitted', 'form.approved'], $webhook->events);
        $this->assertEquals('supersecretkey', $webhook->secret);
    }

    public function test_admin_can_toggle_webhook_status(): void
    {
        $admin = User::where('email', 'admin@eip.local')->first();
        $webhook = Webhook::create([
            'name' => 'Discord 測試',
            'url' => 'https://discord.com/api/webhooks/test',
            'events' => ['form.submitted'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch("/webhooks/{$webhook->id}/toggle");
        $response->assertRedirect('/webhooks');

        $this->assertDatabaseHas('webhooks', [
            'id' => $webhook->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_webhook(): void
    {
        $admin = User::where('email', 'admin@eip.local')->first();
        $webhook = Webhook::create([
            'name' => '待刪除 Webhook',
            'url' => 'https://discord.com/api/webhooks/delete-me',
            'events' => ['room.booked'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/webhooks/{$webhook->id}");
        $response->assertRedirect('/webhooks');

        $this->assertDatabaseMissing('webhooks', [
            'id' => $webhook->id,
        ]);
    }

    public function test_admin_can_ping_webhook(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $admin = User::where('email', 'admin@eip.local')->first();
        $webhook = Webhook::create([
            'name' => 'Slack Ping 測試',
            'url' => 'https://hooks.slack.com/services/PING/TEST',
            'events' => ['form.submitted'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post("/webhooks/{$webhook->id}/ping");
        $response->assertRedirect('/webhooks');
        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.com/services/PING/TEST'
                && str_contains($request['text'], 'EIP 測試訊號');
        });
    }

    public function test_form_submission_dispatches_webhook(): void
    {
        Http::fake([
            'https://discord.com/*' => Http::response(['ok' => true], 200),
        ]);

        Webhook::create([
            'name' => 'Discord 表單審核提醒',
            'url' => 'https://discord.com/api/webhooks/form-channel',
            'events' => ['form.submitted'],
            'is_active' => true,
        ]);

        $employee = User::where('email', 'employee@eip.local')->first();
        $form = Form::where('code', 'LEAVE')->first();

        $response = $this->actingAs($employee)->post("/forms/create/{$form->id}", [
            'title' => '測試請假單 Webhook',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
                'days' => 1,
                'reason' => '家庭因素',
            ],
        ]);

        $response->assertRedirect();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/webhooks/form-channel'
                && str_contains($request['content'], '簽核申請');
        });
    }

    public function test_meeting_room_booking_dispatches_webhook(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        Webhook::create([
            'name' => 'Slack 會議預約推播',
            'url' => 'https://hooks.slack.com/services/room-channel',
            'events' => ['room.booked'],
            'is_active' => true,
        ]);

        $employee = User::where('email', 'employee@eip.local')->first();
        $room = MeetingRoom::first();

        $start = now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0);
        $end = now()->addDays(2)->setHour(15)->setMinute(0)->setSecond(0);

        $response = $this->actingAs($employee)->post('/meeting-rooms/bookings', [
            'meeting_room_id' => $room->id,
            'title' => '產品戰略同步會議',
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
            'attendees_count' => 6,
            'description' => '討論 Q4 規劃',
        ]);

        $response->assertRedirect();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.com/services/room-channel'
                && str_contains($request['text'], '產品戰略同步會議');
        });
    }
}
