<?php

namespace Tests\Feature;

use App\Models\LoginEvent;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginActivityTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';
    private const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    private function emp(): User
    {
        return User::where('email', 'employee@eip.local')->firstOrFail();
    }

    private function loginAs(string $ua, string $password = 'password', string $email = 'employee@eip.local')
    {
        RateLimiter::clear(strtolower($email).'|127.0.0.1');

        return $this->withHeader('User-Agent', $ua)->post('/login', ['email' => $email, 'password' => $password]);
    }

    private function securityNotices(User $u)
    {
        return $u->notifications()->where('data', 'like', '%"type":"security"%')->get();
    }

    public function test_successful_and_failed_logins_are_recorded(): void
    {
        $this->loginAs(self::CHROME_MAC, 'wrong');
        $this->loginAs(self::CHROME_MAC);

        $events = LoginEvent::where('user_id', $this->emp()->id)->orderBy('id')->get();
        $this->assertSame(['failed', 'success'], $events->pluck('event')->all());
        $this->assertSame('127.0.0.1', $events[1]->ip);
        $this->assertSame('Chrome · macOS', LoginEvent::describeAgent($events[1]->user_agent));
    }

    public function test_failed_login_for_unknown_email_is_recorded_without_user(): void
    {
        $this->loginAs(self::CHROME_MAC, 'x', 'nobody@example.com');

        $e = LoginEvent::latest('id')->first();
        $this->assertNull($e->user_id);
        $this->assertSame('nobody@example.com', $e->email);
    }

    public function test_first_login_does_not_alert_but_new_device_does(): void
    {
        $emp = $this->emp();
        $emp->notifications()->delete();

        $this->loginAs(self::CHROME_MAC);                                   // 第一次登入:不提醒
        $this->post('/logout');
        $this->assertCount(0, $this->securityNotices($emp));

        $this->loginAs(self::CHROME_MAC);                                   // 同一個瀏覽器:不提醒
        $this->post('/logout');
        $this->assertCount(0, $this->securityNotices($emp));

        $this->loginAs(self::SAFARI_IPHONE);                                // 新裝置:提醒
        $notices = $this->securityNotices($emp);
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('Safari · iOS', $notices[0]->data['message']);
    }

    public function test_five_failures_notify_user_and_ten_notify_admins(): void
    {
        $emp = $this->emp();
        $admin = User::where('email', 'admin@eip.local')->first();
        $emp->notifications()->delete();
        $admin->notifications()->delete();

        for ($i = 1; $i <= 4; $i++) {
            $this->loginAs(self::CHROME_MAC, 'bad');
        }
        $this->assertCount(0, $this->securityNotices($emp));

        $this->loginAs(self::CHROME_MAC, 'bad');                            // 第 5 次
        $this->assertCount(1, $this->securityNotices($emp));
        $this->assertCount(0, $this->securityNotices($admin));

        for ($i = 6; $i <= 9; $i++) {
            $this->loginAs(self::CHROME_MAC, 'bad');
        }
        $this->assertCount(1, $this->securityNotices($emp), '不重複通知');

        $this->loginAs(self::CHROME_MAC, 'bad');                            // 第 10 次
        $this->assertCount(1, $this->securityNotices($admin));
        $this->assertStringContainsString($emp->email, $this->securityNotices($admin)[0]->data['message']);
    }

    public function test_successful_login_resets_the_failure_streak(): void
    {
        $emp = $this->emp();
        $emp->notifications()->delete();

        for ($i = 0; $i < 4; $i++) {
            $this->loginAs(self::CHROME_MAC, 'bad');
        }
        $this->loginAs(self::CHROME_MAC);
        $this->post('/logout');
        for ($i = 0; $i < 4; $i++) {
            $this->loginAs(self::CHROME_MAC, 'bad');
        }

        $this->assertCount(0, $this->securityNotices($emp), '成功登入後重新計算,4+4 不該觸發');
    }

    public function test_wrong_two_factor_code_is_recorded(): void
    {
        $emp = $this->emp();
        $emp->forceFill(['two_factor_secret' => app(\App\Services\TwoFactorService::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();

        $this->loginAs(self::CHROME_MAC);
        $this->post(route('two-factor.challenge'), ['code' => '000000']);

        $this->assertDatabaseHas('login_events', ['user_id' => $emp->id, 'event' => 'two_factor_failed']);
    }

    public function test_profile_lists_only_own_events(): void
    {
        $this->loginAs(self::CHROME_MAC, 'password', 'manager@eip.local');
        $this->post('/logout');
        $this->loginAs(self::CHROME_MAC);

        $this->get(route('profile.edit'))->assertInertia(fn ($p) => $p->has('loginEvents', 1)->where('loginEvents.0.device', 'Chrome · macOS')->where('loginEvents.0.event', 'success'));
    }

    public function test_logout_other_devices_requires_password_and_audits(): void
    {
        $emp = $this->emp();

        $this->actingAs($emp)->post(route('profile.logout-other-devices'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($emp)->post(route('profile.logout-other-devices'), ['password' => 'password'])->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', ['action' => 'logout_other_devices']);
        $this->assertTrue(Hash::check('password', $emp->fresh()->password), '密碼本身不變');
    }

    public function test_changing_password_invalidates_other_session_but_keeps_current(): void
    {
        $emp = $this->emp();

        // 裝置 A 登入
        $this->loginAs(self::CHROME_MAC);
        $this->get('/dashboard')->assertOk();
        $deviceASession = $this->app['session']->all();

        // 對照組:改密碼之前,重放裝置 A 的 session 應該仍是登入狀態(確保後面的登出不是假陽性)
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withSession($deviceASession)->get('/dashboard')->assertOk();

        // 裝置 B(模擬另一個 session)改密碼
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->loginAs(self::SAFARI_IPHONE);
        $this->get('/dashboard')->assertOk();
        $this->put(route('password.update'), ['current_password' => 'password', 'password' => 'Changed12345', 'password_confirmation' => 'Changed12345'])->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertOk();   // 改密碼的這台仍登入

        // 回到裝置 A 的 session:密碼 hash 已變 → 被登出
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withSession($deviceASession)->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_old_events_are_pruned_by_schedule_closure(): void
    {
        $emp = $this->emp();
        LoginEvent::create(['user_id' => $emp->id, 'event' => 'success'])->forceFill(['created_at' => now()->subDays(200)])->save();
        LoginEvent::create(['user_id' => $emp->id, 'event' => 'success']);

        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => $e->description === 'eip-prune-login-events');
        $this->assertNotNull($event);
        $event->run($this->app);

        $this->assertSame(1, LoginEvent::count());
    }
}
