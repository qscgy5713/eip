<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $engineer;
    protected User $otherUser;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'role' => 'employee',
        ]);
        $this->engineer = User::factory()->create([
            'role' => 'employee',
        ]);
        $this->otherUser = User::factory()->create([
            'role' => 'employee',
        ]);
        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_user_can_view_tickets_index(): void
    {
        Ticket::create([
            'ticket_number' => 'TK-20261012-0001',
            'user_id' => $this->user->id,
            'category' => 'it_hardware',
            'priority' => 'normal',
            'status' => 'open',
            'title' => '筆電鍵盤無法輸入',
            'description' => '部分字母鍵無反應',
        ]);

        $response = $this->actingAs($this->user)->get(route('tickets.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 1)
            ->has('stats')
        );
    }

    public function test_user_can_create_ticket(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('screenshot.png', 100, 'image/png');

        $payload = [
            'category' => 'it_network',
            'priority' => 'high',
            'title' => '會議室 Wi-Fi 訊號異常斷線',
            'description' => '每五分鐘斷開一次連線',
            'location' => '5F 大會議室',
            'attachments' => [$file],
        ];

        $response = $this->actingAs($this->user)->post(route('tickets.store'), $payload);

        $ticket = Ticket::latest()->first();
        $this->assertNotNull($ticket);
        $response->assertRedirect(route('tickets.show', $ticket->id));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'user_id' => $this->user->id,
            'category' => 'it_network',
            'priority' => 'high',
            'status' => 'open',
            'title' => '會議室 Wi-Fi 訊號異常斷線',
            'location' => '5F 大會議室',
        ]);

        $this->assertStringStartsWith('TK-', $ticket->ticket_number);

        // 管理員收到新工單通知
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->admin->id,
            'type' => \App\Notifications\EipSystemNotification::class,
        ]);
    }

    public function test_idor_protection_for_ticket_details(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TK-20261012-0002',
            'user_id' => $this->user->id,
            'category' => 'facility',
            'priority' => 'normal',
            'status' => 'open',
            'title' => '座位旁插座鬆脫',
            'description' => '插頭接觸不良',
        ]);

        // 提單人可查看
        $response = $this->actingAs($this->user)->get(route('tickets.show', $ticket->id));
        $response->assertStatus(200);

        // 無關同仁禁止查看 (403 IDOR 防護)
        $response2 = $this->actingAs($this->otherUser)->get(route('tickets.show', $ticket->id));
        $response2->assertStatus(403);

        // 管理員可查看
        $response3 = $this->actingAs($this->admin)->get(route('tickets.show', $ticket->id));
        $response3->assertStatus(200);
    }

    public function test_admin_can_assign_ticket_to_engineer(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TK-20261012-0003',
            'user_id' => $this->user->id,
            'category' => 'it_hardware',
            'priority' => 'normal',
            'status' => 'open',
            'title' => '更換滑鼠',
            'description' => '滾輪故障',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('tickets.assign', $ticket->id), [
            'assigned_to' => $this->engineer->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($this->engineer->id, $ticket->fresh()->assigned_to);
        $this->assertEquals('in_progress', $ticket->fresh()->status);

        // 工程師收到指派通知
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->engineer->id,
            'type' => \App\Notifications\EipSystemNotification::class,
        ]);
    }

    public function test_engineer_can_update_status_to_resolved_and_notifies_user(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TK-20261012-0004',
            'user_id' => $this->user->id,
            'assigned_to' => $this->engineer->id,
            'category' => 'it_hardware',
            'priority' => 'normal',
            'status' => 'in_progress',
            'title' => '安裝額外記憶體',
            'description' => '擴充至 32GB',
        ]);

        $response = $this->actingAs($this->engineer)->patch(route('tickets.update-status', $ticket->id), [
            'status' => 'resolved',
        ]);

        $response->assertRedirect();
        $updated = $ticket->fresh();
        $this->assertEquals('resolved', $updated->status);
        $this->assertNotNull($updated->resolved_at);

        // 提單同仁收到完修通知
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->user->id,
            'type' => \App\Notifications\EipSystemNotification::class,
        ]);
    }

    public function test_ticket_reply_and_internal_note_visibility(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TK-20261012-0005',
            'user_id' => $this->user->id,
            'assigned_to' => $this->engineer->id,
            'category' => 'it_software',
            'priority' => 'normal',
            'status' => 'pending_user',
            'title' => '微軟授權開通',
            'description' => '需重設金鑰',
        ]);

        // 1. 同仁回覆訊息（自動將 pending_user 改回 in_progress）
        $this->actingAs($this->user)->post(route('tickets.reply', $ticket->id), [
            'message' => '已重新開機，但仍然提示無授權。',
        ]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->user->id,
            'message' => '已重新開機，但仍然提示無授權。',
            'is_internal' => false,
        ]);
        $this->assertEquals('in_progress', $ticket->fresh()->status);

        // 2. 工程師新增內部備註
        $this->actingAs($this->engineer)->post(route('tickets.reply', $ticket->id), [
            'message' => '內部備註：AD 帳號同步有延遲，預計 10 分鐘後生效。',
            'is_internal' => true,
        ]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => '內部備註：AD 帳號同步有延遲，預計 10 分鐘後生效。',
            'is_internal' => true,
        ]);

        // 驗證一般同仁查看時看不到內部備註
        $response = $this->actingAs($this->user)->get(route('tickets.show', $ticket->id));
        $response->assertInertia(fn ($page) => $page
            ->has('replies', 1) // 只有第 1 則公開留言
        );

        // 驗證工程師可查看全部備註
        $response2 = $this->actingAs($this->engineer)->get(route('tickets.show', $ticket->id));
        $response2->assertInertia(fn ($page) => $page
            ->has('replies', 2)
        );
    }

    public function test_user_can_rate_and_close_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TK-20261012-0006',
            'user_id' => $this->user->id,
            'assigned_to' => $this->engineer->id,
            'category' => 'facility',
            'priority' => 'normal',
            'status' => 'resolved',
            'title' => '冷氣出風口異音',
            'description' => '請檢查風扇',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->post(route('tickets.rate', $ticket->id), [
            'satisfaction_rating' => 5,
            'satisfaction_feedback' => '工程師動作迅速，非常有禮貌！',
        ]);

        $response->assertRedirect();
        $updated = $ticket->fresh();
        $this->assertEquals('closed', $updated->status);
        $this->assertEquals(5, $updated->satisfaction_rating);
        $this->assertEquals('工程師動作迅速，非常有禮貌！', $updated->satisfaction_feedback);
        $this->assertNotNull($updated->closed_at);
    }

    public function test_global_search_can_find_tickets(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TK-20261012-9999',
            'user_id' => $this->user->id,
            'category' => 'it_hardware',
            'priority' => 'urgent',
            'status' => 'open',
            'title' => '伺服器機房警報蜂鳴器長鳴',
            'description' => '溫度過高警告',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('global-search', ['q' => '機房警報']));

        $response->assertStatus(200);
        $response->assertJsonPath('results.tickets.items.0.title', "【工單】{$ticket->ticket_number} - {$ticket->title}");
    }
}
