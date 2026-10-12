<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorService $tf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        $this->tf = app(TwoFactorService::class);
    }

    private function user(string $role = 'employee'): User
    {
        return User::where('email', "$role@eip.local")->firstOrFail();
    }

    /** 對已啟用 2FA 的使用者回傳目前有效驗證碼(跳過已使用的時間窗) */
    private function enable(User $user): string
    {
        $secret = $this->tf->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [$this->tf->hashRecoveryCode('aaaaa-bbbbb'), $this->tf->hashRecoveryCode('ccccc-ddddd')],
        ])->save();

        return $secret;
    }

    public function test_totp_matches_rfc6238_vector(): void
    {
        // RFC 6238 附錄 B:密鑰 "12345678901234567890"、T=59 → 8 位 94287082,取後 6 位 287082
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', $this->tf->codeAt($secret, intdiv(59, 30)));
        $this->assertSame('081804', $this->tf->codeAt($secret, intdiv(1111111109, 30)));
        $this->assertNotNull($this->tf->verify($secret, '287082', null, 59));
        $this->assertNull($this->tf->verify($secret, '287082', null, 59 + 300), '過期驗證碼不可通過');
        $this->assertNull($this->tf->verify($secret, 'abcdef', null, 59));
        $this->assertNull($this->tf->verify($secret, '28708', null, 59));
    }

    public function test_replayed_code_is_rejected(): void
    {
        $secret = $this->tf->generateSecret();
        $code = $this->tf->codeAt($secret, $this->tf->currentStep());
        $step = $this->tf->verify($secret, $code);
        $this->assertNotNull($step);
        $this->assertNull($this->tf->verify($secret, $code, $step));
    }

    public function test_user_can_enable_two_factor_and_gets_recovery_codes(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('two-factor.enable'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($user)->post(route('two-factor.enable'), ['password' => 'password'])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());

        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $code = $this->tf->codeAt($user->two_factor_secret, $this->tf->currentStep());
        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('two_factor_recovery_codes', fn ($c) => count($c) === 8);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['action' => 'enable_two_factor']);
    }

    public function test_secret_is_encrypted_at_rest_and_hidden(): void
    {
        $user = $this->user();
        $secret = $this->enable($user);

        $raw = \DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertArrayNotHasKey('two_factor_secret', $user->fresh()->toArray());
    }

    public function test_login_requires_second_step_when_enabled(): void
    {
        $user = $this->user();
        $secret = $this->enable($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->get(route('two-factor.challenge'))->assertOk();
        $this->post(route('two-factor.challenge'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = $this->tf->codeAt($secret, $this->tf->currentStep());
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_same_code_cannot_be_used_twice_to_login(): void
    {
        $user = $this->user();
        $secret = $this->enable($user);
        $code = $this->tf->codeAt($secret, $this->tf->currentStep());

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once(): void
    {
        $user = $this->user();
        $this->enable($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.challenge'), ['recovery_code' => 'AAAAA-BBBBB'])->assertRedirect(route('dashboard'));
        $this->assertCount(1, $user->fresh()->two_factor_recovery_codes);
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.challenge'), ['recovery_code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_challenge_is_rate_limited(): void
    {
        $user = $this->user();
        $secret = $this->enable($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.challenge'), ['code' => '000000'])->assertSessionHasErrors('code');
        }
        // 第 6 次即使驗證碼正確也被鎖定
        $code = $this->tf->codeAt($secret, $this->tf->currentStep());
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_page_without_pending_login_redirects(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->post(route('two-factor.challenge'), ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_user_without_two_factor_logs_in_normally(): void
    {
        $this->post('/login', ['email' => 'employee@eip.local', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_disable_requires_password(): void
    {
        $user = $this->user();
        $this->enable($user);

        $this->actingAs($user)->post(route('two-factor.disable'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)->post(route('two-factor.disable'), ['password' => 'password'])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
    }

    public function test_required_role_must_enable_and_cannot_disable(): void
    {
        config(['eip.require_2fa_roles' => ['admin']]);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('profile.edit'));
        $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
        $this->actingAs($this->user('employee'))->get('/dashboard')->assertOk();

        $this->enable($admin);
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->post(route('two-factor.disable'), ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_regenerate_recovery_codes_invalidates_old_ones(): void
    {
        $user = $this->user();
        $this->enable($user);

        $this->actingAs($user)->post(route('two-factor.recovery-codes'), ['password' => 'password'])
            ->assertSessionHas('two_factor_recovery_codes');

        $this->assertFalse($this->tf->consumeRecoveryCode($user->fresh(), 'aaaaa-bbbbb'));
    }

    public function test_profile_page_exposes_state_without_secret_when_enabled(): void
    {
        $user = $this->user();
        $this->enable($user);

        $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
            ->where('twoFactor.enabled', true)
            ->where('twoFactor.pending', null)
            ->where('twoFactor.recoveryRemaining', 2));
    }

    public function test_hr_can_reset_two_factor_for_locked_out_employee(): void
    {
        $employee = $this->user();
        $this->enable($employee);

        $this->actingAs($this->user('employee'))->post(route('org-management.users.reset-two-factor', $employee))->assertForbidden();
        $this->actingAs($this->user('hr'))->post(route('org-management.users.reset-two-factor', $employee))->assertSessionHas('success');

        $employee->refresh();
        $this->assertFalse($employee->hasTwoFactorEnabled());
        $this->assertNull($employee->two_factor_secret);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reset_user_two_factor']);

        // 重設後只需密碼即可登入
        $this->post('/login', ['email' => $employee->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_hr_cannot_reset_admin_two_factor_but_admin_can(): void
    {
        $admin = $this->user('admin');
        $this->enable($admin);

        $this->actingAs($this->user('hr'))->post(route('org-management.users.reset-two-factor', $admin))->assertForbidden();
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->actingAs($admin)->post(route('org-management.users.reset-two-factor', $this->user('hr')))->assertSessionHasErrors('two_factor');
    }

    public function test_org_management_list_flags_two_factor_users(): void
    {
        $this->enable($this->user());

        $this->actingAs($this->user('hr'))->get(route('org-management.index', ['role' => 'employee']))
            ->assertInertia(fn ($p) => $p->where('users.data', fn ($rows) => collect($rows)->contains(fn ($u) => $u['email'] === 'employee@eip.local' && $u['has_two_factor'] === true)));
    }
}
