<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_makes_active_admin_that_must_change_password(): void
    {
        $this->artisan('eip:create-admin', ['email' => 'boss@corp.com', '--name' => '老闆', '--password' => 'Str0ngPassw0rd'])
            ->assertSuccessful();

        $u = User::where('email', 'boss@corp.com')->firstOrFail();
        $this->assertSame('admin', $u->role);
        $this->assertSame('active', $u->status);
        $this->assertTrue($u->must_change_password);
        $this->assertTrue(Hash::check('Str0ngPassw0rd', $u->password));
        $this->assertNotNull($u->department_id);
    }

    public function test_create_admin_rejects_weak_password_and_duplicate_email(): void
    {
        $this->artisan('eip:create-admin', ['email' => 'a@corp.com', '--name' => 'A', '--password' => 'password'])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'a@corp.com']);

        $this->artisan('eip:create-admin', ['email' => 'b@corp.com', '--name' => 'B', '--password' => 'Str0ngPassw0rd'])->assertSuccessful();
        $this->artisan('eip:create-admin', ['email' => 'b@corp.com', '--name' => 'B2', '--password' => 'Str0ngPassw0rd'])->assertFailed();
        $this->assertSame(1, User::where('email', 'b@corp.com')->count());
    }

    public function test_preflight_fails_when_demo_accounts_exist(): void
    {
        $this->seed(EipDatabaseSeeder::class);

        $this->artisan('eip:preflight')
            ->expectsOutputToContain('沒有預設測試帳號')
            ->assertFailed();
    }

    public function test_preflight_flags_missing_demo_accounts_check_as_pass_on_clean_db(): void
    {
        $this->artisan('eip:create-admin', ['email' => 'boss@corp.com', '--name' => '老闆', '--password' => 'Str0ngPassw0rd'])->assertSuccessful();

        $this->artisan('eip:preflight')->expectsOutputToContain('[通過] 沒有預設測試帳號')->run();
    }

    public function test_preflight_reports_debug_mode(): void
    {
        config(['app.debug' => true]);

        $this->artisan('eip:preflight')->expectsOutputToContain('APP_DEBUG=false')->assertFailed();
    }

    public function test_behind_trusted_proxy_assets_and_urls_use_https(): void
    {
        config(['eip.trusted_proxies' => '*']);
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $html = $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'eip.example.com'])->getContent();

        $this->assertStringNotContainsString('http://', preg_replace('#http://www\.w3\.org[^"\']*#', '', $html), '代理後的資源網址不應是 http');
        $this->assertStringContainsString('https://', $html);
    }

    protected function tearDown(): void
    {
        \Illuminate\Http\Middleware\TrustProxies::flushState();
        parent::tearDown();
    }
}
