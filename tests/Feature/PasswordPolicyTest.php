<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasswordPolicyService;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        config(['eip.password_history' => 3, 'eip.password_max_age_days' => 0]);
    }

    private function emp(): User
    {
        return User::where('email', 'employee@eip.local')->firstOrFail();
    }

    private function change(User $u, string $new, string $current = 'password')
    {
        return $this->actingAs($u)->put(route('password.update'), [
            'current_password' => $current, 'password' => $new, 'password_confirmation' => $new,
        ]);
    }

    public function test_cannot_reuse_current_password(): void
    {
        $emp = $this->emp();
        $emp->update(['password' => Hash::make('Current123')]);

        $this->change($emp->fresh(), 'Current123', 'Current123')->assertSessionHasErrors('password');
        $this->assertSame(0, DB::table('password_histories')->count(), '被擋下時不應留下歷史');
    }

    public function test_cannot_reuse_recent_passwords_but_can_after_window(): void
    {
        $emp = $this->emp();
        $emp->update(['password' => Hash::make('Alpha1234')]);

        $this->change($emp, 'Beta12345', 'Alpha1234')->assertSessionHasNoErrors();      // 歷史:Alpha
        $this->change($emp->fresh(), 'Gamma1234', 'Beta12345')->assertSessionHasNoErrors(); // 歷史:Alpha, Beta
        $this->change($emp->fresh(), 'Alpha1234', 'Gamma1234')->assertSessionHasErrors('password');
        $this->change($emp->fresh(), 'Beta12345', 'Gamma1234')->assertSessionHasErrors('password');
        $this->change($emp->fresh(), 'Gamma1234', 'Gamma1234')->assertSessionHasErrors('password');

        // 再換兩次,Alpha 掉出最近 3 組之外後可以重用
        $this->change($emp->fresh(), 'Delta1234', 'Gamma1234')->assertSessionHasNoErrors(); // 歷史:Alpha,Beta,Gamma
        $this->change($emp->fresh(), 'Echo12345', 'Delta1234')->assertSessionHasNoErrors(); // 歷史:Beta,Gamma,Delta
        $this->change($emp->fresh(), 'Alpha1234', 'Echo12345')->assertSessionHasNoErrors();
    }

    public function test_history_is_trimmed_to_configured_size(): void
    {
        $emp = $this->emp();
        $svc = app(PasswordPolicyService::class);
        for ($i = 1; $i <= 8; $i++) {
            $emp->update(['password' => Hash::make("Pw{$i}aaaaaa")]);
            $svc->remember($emp->fresh());
        }

        $this->assertSame(3, DB::table('password_histories')->where('user_id', $emp->id)->count());
    }

    public function test_history_disabled_when_zero(): void
    {
        config(['eip.password_history' => 0]);
        $emp = $this->emp();

        $this->change($emp, 'Newpass123')->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('password_histories')->count());
        $this->change($emp->fresh(), 'Newpass123', 'Newpass123')->assertSessionHasNoErrors();  // 沒啟用就不擋
    }

    public function test_admin_reset_records_old_password(): void
    {
        $emp = $this->emp();
        $this->actingAs(User::where('email', 'hr@eip.local')->first())->post(route('org-management.users.reset-password', $emp), [
            'password' => 'HrSet12345', 'password_confirmation' => 'HrSet12345',
        ])->assertSessionHasNoErrors();

        // 同仁被迫換密碼時,不能換回舊的初始密碼
        $this->actingAs($emp->fresh())->post(route('password.force.update'), [
            'current_password' => 'HrSet12345', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    public function test_expiry_forces_change_and_clears_after_change(): void
    {
        config(['eip.password_max_age_days' => 90]);
        $emp = $this->emp();
        $emp->forceFill(['password_changed_at' => now()->subDays(91)])->save();

        $this->actingAs($emp)->get('/dashboard')->assertRedirect(route('password.force'));
        $this->assertTrue($emp->fresh()->must_change_password);

        $this->actingAs($emp->fresh())->post(route('password.force.update'), [
            'current_password' => 'password', 'password' => 'Fresh12345', 'password_confirmation' => 'Fresh12345',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($emp->fresh())->get('/dashboard')->assertOk();
    }

    public function test_not_yet_expired_or_disabled_policy_does_not_interfere(): void
    {
        $emp = $this->emp();
        $emp->forceFill(['password_changed_at' => now()->subDays(400)])->save();
        $this->actingAs($emp)->get('/dashboard')->assertOk();         // 政策關閉

        config(['eip.password_max_age_days' => 90]);
        $emp->forceFill(['password_changed_at' => now()->subDays(30)])->save();
        $this->actingAs($emp->fresh())->get('/dashboard')->assertOk(); // 尚未到期
    }

    public function test_never_changed_password_uses_account_creation_date(): void
    {
        config(['eip.password_max_age_days' => 90]);
        $svc = app(PasswordPolicyService::class);
        $u = $this->emp();

        $u->forceFill(['password_changed_at' => null, 'created_at' => now()->subDays(10)])->save();
        $this->assertFalse($svc->isExpired($u->fresh()));
        $this->assertGreaterThan(70, $svc->daysUntilExpiry($u->fresh()));

        $u->forceFill(['password_changed_at' => null, 'created_at' => now()->subDays(200)])->save();
        $this->assertTrue($svc->isExpired($u->fresh()));
    }

    public function test_forgot_password_reset_cannot_reuse_current_password(): void
    {
        $emp = $this->emp();
        $token = \Illuminate\Support\Facades\Password::createToken($emp);

        $this->post('/reset-password', ['token' => $token, 'email' => $emp->email, 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors();
        $this->assertTrue(Hash::check('password', $emp->fresh()->password));
    }
}
