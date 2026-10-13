<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        RateLimiter::clear('x');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'employee@eip.local', 'password' => 'wrong' . $i]);
        }

        // 第 6 次即使密碼正確也要被擋
        $this->post('/login', ['email' => 'employee@eip.local', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_does_not_reveal_whether_the_account_exists(): void
    {
        $this->post('/login', ['email' => 'employee@eip.local', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $a = session('errors')->first('email');
        RateLimiter::clear('');
        $this->post('/login', ['email' => 'nobody@eip.local', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $b = session('errors')->first('email');

        $this->assertNotEmpty($a);
        $this->assertSame($a, $b);
    }

    public function test_forgot_password_gives_same_answer_for_unknown_email(): void
    {
        $known = $this->post('/forgot-password', ['email' => 'employee@eip.local']);
        $knownStatus = session('status');
        $knownErrors = session('errors')?->all() ?? [];
        $this->flushSession();
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@eip.local']);

        $this->assertSame($knownStatus, session('status'), '已註冊與未註冊的回應不應不同(帳號列舉)');
        $this->assertSame($knownErrors, session('errors')?->all() ?? []);
    }

    public function test_session_id_changes_after_login(): void
    {
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => 'employee@eip.local', 'password' => 'password']);

        $this->assertNotSame($before, session()->getId(), '登入後必須換新 session id,防止 session fixation');
    }

    public function test_profile_update_cannot_change_role_or_status(): void
    {
        $u = User::where('email', 'employee@eip.local')->first();

        $this->actingAs($u)->patch('/profile', ['name' => '新名字', 'email' => 'employee@eip.local', 'role' => 'admin', 'status' => 'active', 'must_change_password' => false, 'department_id' => 1]);

        $this->assertSame('employee', $u->fresh()->role);
    }

    public function test_hr_cannot_create_or_promote_admin_and_cannot_edit_admin(): void
    {
        $hr = User::where('email', 'hr@eip.local')->first();
        $admin = User::where('email', 'admin@eip.local')->first();
        $emp = User::where('email', 'employee@eip.local')->first();

        $this->actingAs($hr)->post(route('org-management.users.store'), ['name' => 'X', 'email' => 'x@corp.com', 'password' => 'Str0ngPassw0rd', 'role' => 'admin', 'department_id' => 1]);
        $this->assertDatabaseMissing('users', ['email' => 'x@corp.com', 'role' => 'admin']);

        $this->actingAs($hr)->put(route('org-management.users.update', $emp), ['name' => $emp->name, 'email' => $emp->email, 'role' => 'admin', 'department_id' => $emp->department_id, 'status' => 'active']);
        $this->assertSame('employee', $emp->fresh()->role);

        $this->actingAs($hr)->put(route('org-management.users.update', $admin), ['name' => 'hacked', 'email' => $admin->email, 'role' => 'employee', 'department_id' => $admin->department_id, 'status' => 'active']);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_resigned_user_session_is_rejected(): void
    {
        $u = User::where('email', 'employee@eip.local')->first();
        $this->actingAs($u)->get('/dashboard')->assertOk();

        $u->update(['status' => 'resigned']);

        $this->actingAs($u->fresh())->get('/dashboard')->assertRedirect();
    }

    public function test_login_redirect_param_cannot_send_user_off_site(): void
    {
        $res = $this->post('/login?redirect=https://evil.example', ['email' => 'employee@eip.local', 'password' => 'password', 'redirect' => 'https://evil.example']);

        $this->assertStringNotContainsString('evil.example', (string) $res->headers->get('Location'));
    }

    public function test_forgot_password_is_throttled_and_still_uniform(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'employee@eip.local']);
        }

        $this->post('/forgot-password', ['email' => 'employee@eip.local'])->assertStatus(429);
    }

    public function test_forgot_password_still_sends_mail_to_real_accounts_only(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->post('/forgot-password', ['email' => 'employee@eip.local']);
        $this->post('/forgot-password', ['email' => 'nobody@eip.local']);

        \Illuminate\Support\Facades\Notification::assertSentTo(User::where('email', 'employee@eip.local')->first(), \Illuminate\Auth\Notifications\ResetPassword::class);
        \Illuminate\Support\Facades\Notification::assertCount(1);
    }
}
