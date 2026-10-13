<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\EipSystemNotification;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

class PushAndPasskeyTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPkey', 'auth' => 'authkey']];
    }

    public function test_subscribe_stores_subscription_for_current_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push/subscribe', $this->payload())->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id]);
    }

    public function test_subscribe_rejects_non_push_service_hosts_and_plain_http(): void
    {
        $user = User::factory()->create();
        foreach ([
            'https://evil.example.com/steal',
            'https://169.254.169.254/latest/meta-data',
            'https://fcm.googleapis.com.evil.com/x',
            'http://fcm.googleapis.com/fcm/send/abc',
        ] as $endpoint) {
            $this->actingAs($user)->postJson('/push/subscribe', $this->payload($endpoint))->assertStatus(422);
        }
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_same_device_is_reassigned_to_the_new_login_and_not_duplicated(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->postJson('/push/subscribe', $this->payload())->assertOk();
        $this->actingAs($b)->postJson('/push/subscribe', $this->payload())->assertOk();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($b->id, PushSubscription::first()->user_id);
    }

    public function test_unsubscribe_only_removes_own_subscription(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->postJson('/push/subscribe', $this->payload())->assertOk();

        $this->actingAs($b)->postJson('/push/unsubscribe', ['endpoint' => $this->payload()['endpoint']])->assertOk();
        $this->assertSame(1, PushSubscription::count());

        $this->actingAs($a)->postJson('/push/unsubscribe', ['endpoint' => $this->payload()['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_push_routes_require_login(): void
    {
        $this->postJson('/push/subscribe', $this->payload())->assertUnauthorized();
        $this->postJson('/push/test')->assertUnauthorized();
    }

    public function test_notification_uses_push_channel_only_when_configured_and_subscribed(): void
    {
        $user = User::factory()->create();
        $n = new EipSystemNotification('t', 'm');

        config(['eip.webpush.public_key' => null, 'eip.webpush.private_key' => null]);
        $this->assertNotContains(WebPushChannel::class, $n->via($user));

        config(['eip.webpush.public_key' => 'pub', 'eip.webpush.private_key' => 'priv']);
        $this->assertNotContains(WebPushChannel::class, $n->via($user), '沒有訂閱的裝置不應走推播');

        $this->actingAs($user)->postJson('/push/subscribe', $this->payload())->assertOk();
        $this->assertContains(WebPushChannel::class, $n->via($user->fresh()));

        $user->update(['push_notifications' => false]);
        $this->assertNotContains(WebPushChannel::class, $n->via($user->fresh()));
    }

    public function test_send_without_keys_is_a_safe_noop(): void
    {
        config(['eip.webpush.public_key' => null, 'eip.webpush.private_key' => null]);
        $user = User::factory()->create();

        $this->assertSame(0, app(WebPushService::class)->sendToUser($user, 't', 'm'));
    }

    public function test_profile_page_exposes_push_and_passkey_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertInertia(fn ($page) => $page->has('push.devices')->has('passkeys'));
    }

    public function test_passkey_login_options_are_public_but_management_requires_login(): void
    {
        $this->getJson('/passkeys/login/options')->assertOk()->assertJsonStructure(['options']);
        $this->getJson('/user/passkeys/options')->assertUnauthorized();
        $this->deleteJson('/user/passkeys/1')->assertUnauthorized();
    }

    public function test_inactive_account_cannot_log_in_with_a_valid_passkey(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        $passkey = new Passkey(['name' => 'x', 'credential_id' => 'c', 'credential' => []]);
        $passkey->setRelation('user', $user);

        $this->expectException(ValidationException::class);
        Passkeys::allowsLogin(Request::create('/passkeys/login', 'POST'), $passkey);
    }

    public function test_active_account_is_allowed_by_passkey_policy(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $passkey = new Passkey(['name' => 'x', 'credential_id' => 'c', 'credential' => []]);
        $passkey->setRelation('user', $user);

        $this->assertTrue(Passkeys::allowsLogin(Request::create('/passkeys/login', 'POST'), $passkey));
    }
}
