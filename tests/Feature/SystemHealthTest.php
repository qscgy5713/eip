<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    private function user(string $role): User
    {
        return User::where('email', "$role@eip.local")->firstOrFail();
    }

    private function failJob(string $error = "Swift_TransportException: Connection refused\n#0 stack"): string
    {
        $id = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $id, 'connection' => 'redis', 'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Mail\\EipNotificationMail', 'uuid' => $id, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => ['commandName' => 'stdClass', 'command' => serialize(new \stdClass)]]),
            'exception' => $error, 'failed_at' => now(),
        ]);

        return $id;
    }

    public function test_only_admin_can_access(): void
    {
        foreach (['employee', 'manager', 'hr'] as $role) {
            $this->actingAs($this->user($role))->get(route('system-health.index'))->assertForbidden();
            $this->actingAs($this->user($role))->post(route('system-health.retry-all'))->assertForbidden();
            $this->actingAs($this->user($role))->delete(route('system-health.forget', (string) Str::uuid()))->assertForbidden();
        }
        $this->actingAs($this->user('admin'))->get(route('system-health.index'))->assertOk();
    }

    public function test_heartbeats_reflect_cache(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('system-health.index'))
            ->assertInertia(fn ($p) => $p->where('checks.queue.ok', false)->where('checks.scheduler.ok', false)->where('checks.database.ok', true));

        Cache::put('eip:queue_heartbeat', time() - 10);
        Cache::put('eip:scheduler_heartbeat', time() - 600);

        $this->actingAs($admin)->get(route('system-health.index'))
            ->assertInertia(fn ($p) => $p->where('checks.queue.ok', true)->where('checks.scheduler.ok', false)->where('checks.scheduler.seconds_ago', fn ($s) => $s >= 600));
    }

    public function test_failed_jobs_are_listed_without_leaking_payload(): void
    {
        $this->failJob();

        $this->actingAs($this->user('admin'))->get(route('system-health.index'))
            ->assertInertia(fn ($p) => $p->where('queue.failed_total', 1)->has('failedJobs', 1)
                ->where('failedJobs.0.job', 'App\\Mail\\EipNotificationMail')
                ->where('failedJobs.0.error', 'Swift_TransportException: Connection refused')
                ->missing('failedJobs.0.payload'));
    }

    public function test_mail_password_is_never_exposed(): void
    {
        config(['mail.mailers.smtp.password' => 'SUPER-SECRET-PW', 'mail.default' => 'smtp']);

        $json = json_encode($this->actingAs($this->user('admin'))->get(route('system-health.index'))->viewData('page'));

        $this->assertStringNotContainsString('SUPER-SECRET-PW', $json);
    }

    public function test_retry_requeues_and_removes_from_failed(): void
    {
        Queue::fake();
        $id = $this->failJob();

        $this->actingAs($this->user('admin'))->post(route('system-health.retry', $id))->assertSessionHas('success');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'retry_failed_job']);
    }

    public function test_retry_unknown_or_malformed_id(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('system-health.retry', (string) Str::uuid()))->assertSessionHas('error');
        $this->actingAs($admin)->post('/system-health/failed-jobs/not-a-uuid/retry')->assertNotFound();
        $this->actingAs($admin)->delete('/system-health/failed-jobs/not-a-uuid')->assertNotFound();
    }

    public function test_retry_all_and_forget(): void
    {
        Queue::fake();
        $a = $this->failJob();
        $b = $this->failJob();
        $c = $this->failJob();
        $admin = $this->user('admin');

        $this->actingAs($admin)->delete(route('system-health.forget', $c))->assertSessionHas('success');
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $c]);

        $this->actingAs($admin)->post(route('system-health.retry-all'))->assertSessionHas('success');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'retry_all_failed_jobs']);
    }

    public function test_corrupt_failed_job_reports_error_instead_of_500(): void
    {
        $id = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $id, 'connection' => 'redis', 'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Broken', 'data' => ['command' => 'garbage-not-serialized']]),
            'exception' => 'x', 'failed_at' => now(),
        ]);

        $this->actingAs($this->user('admin'))->post(route('system-health.retry', $id))->assertSessionHas('error');
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $id]);
    }

    public function test_backup_status_reports_missing(): void
    {
        $this->actingAs($this->user('admin'))->get(route('system-health.index'))
            ->assertInertia(fn ($p) => $p->has('checks.backup.found')->has('checks.disk.free_gb'));
    }
}
