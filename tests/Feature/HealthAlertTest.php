<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HealthAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        Cache::flush();
        // 磁碟用量與備份取決於跑測試的機器,固定成正常值
        $this->partialMock(\App\Services\SystemHealth::class, function ($m) {
            $m->shouldReceive('disk')->andReturn(['free_gb' => 100.0, 'total_gb' => 200.0, 'used_percent' => 50]);
            $m->shouldReceive('latestBackup')->andReturn(['found' => true, 'file' => 'x', 'at' => now()->toDateTimeString(), 'hours_ago' => 1]);
        });
    }

    private function healthy(): void
    {
        Cache::put('eip:queue_heartbeat', time(), 600);
        Cache::put('eip:scheduler_heartbeat', time(), 600);
    }

    public function test_healthy_system_sends_nothing(): void
    {
        Mail::fake();
        $this->healthy();

        $this->artisan('eip:health-alert')->expectsOutputToContain('一切正常')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_dead_workers_send_alert_to_active_admins_only(): void
    {
        $captured = null;
        Mail::shouldReceive('raw')->once()->andReturnUsing(function ($body, $cb) use (&$captured) {
            $m = new class { public array $to = []; public ?string $subject = null;
                public function to($t) { $this->to = (array) $t; return $this; }
                public function subject($s) { $this->subject = $s; return $this; } };
            $cb($m);
            $captured = [$body, $m];
        });

        $this->artisan('eip:health-alert')->assertFailed();

        $this->assertStringContainsString('queue worker', $captured[0]);
        $this->assertStringContainsString('scheduler', $captured[0]);
        $this->assertSame(['admin@eip.local'], $captured[1]->to);
        $this->assertStringContainsString('警報', $captured[1]->subject);
    }

    public function test_same_problem_is_not_resent_within_six_hours(): void
    {
        Mail::shouldReceive('raw')->once();

        $this->artisan('eip:health-alert')->assertFailed();
        $this->artisan('eip:health-alert')->assertFailed();
    }

    public function test_recovery_sends_one_recovery_mail(): void
    {
        Mail::shouldReceive('raw')->twice();

        $this->artisan('eip:health-alert')->assertFailed();
        $this->healthy();
        $this->artisan('eip:health-alert')->assertSuccessful();
        $this->artisan('eip:health-alert')->assertSuccessful();
    }

    public function test_failed_jobs_are_reported(): void
    {
        $this->healthy();
        \DB::table('failed_jobs')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

        $this->artisan('eip:health-alert --dry-run')->expectsOutputToContain('1 筆失敗任務')->assertFailed();
    }

    public function test_dry_run_never_sends_mail(): void
    {
        Mail::fake();

        $this->artisan('eip:health-alert --dry-run')->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_mail_failure_does_not_mark_as_notified(): void
    {
        Mail::shouldReceive('raw')->twice()->andThrow(new \RuntimeException('smtp down'));

        $this->artisan('eip:health-alert')->assertFailed();
        $this->artisan('eip:health-alert')->assertFailed();
    }

    public function test_high_disk_usage_and_stale_backup_are_reported(): void
    {
        $this->partialMock(\App\Services\SystemHealth::class, function ($m) {
            $m->shouldReceive('disk')->andReturn(['free_gb' => 5.0, 'total_gb' => 100.0, 'used_percent' => 95]);
            $m->shouldReceive('latestBackup')->andReturn(['found' => true, 'file' => 'x', 'at' => 'old', 'hours_ago' => 72]);
        });
        $this->healthy();

        $this->artisan('eip:health-alert --dry-run')
            ->expectsOutputToContain('磁碟已使用 95%')
            ->expectsOutputToContain('72 小時前')
            ->assertFailed();
    }
}
