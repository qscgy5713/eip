<?php

namespace Tests\Feature;

use App\Mail\EipNotificationMail;
use App\Models\ApprovalRecord;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemindPendingApprovalsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        ApprovalRecord::query()->delete();
        FormRequest::query()->delete();
        $this->manager = User::where('email', 'manager@eip.local')->first();
        $this->employee = User::where('email', 'employee@eip.local')->first();
        $this->manager->notifications()->delete();
    }

    private function pending(User $approver, int $daysAgo, string $title = '請假', string $requestStatus = 'pending', string $recordStatus = 'pending'): ApprovalRecord
    {
        $req = FormRequest::create([
            'form_id' => Form::where('code', 'LEAVE')->value('id'), 'user_id' => $this->employee->id,
            'request_no' => 'RM-'.uniqid(), 'title' => $title, 'data' => [],
            'status' => $requestStatus, 'current_step' => 1, 'total_steps' => 1,
        ]);
        $rec = ApprovalRecord::create(['form_request_id' => $req->id, 'step' => 1, 'step_title' => '主管', 'approver_id' => $approver->id, 'status' => $recordStatus]);
        $rec->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $rec;
    }

    private function reminders(User $u)
    {
        return $u->notifications()->where('data', 'like', '%approval_reminder%')->get();
    }

    public function test_overdue_approver_gets_one_digest_for_many_records(): void
    {
        Mail::fake();
        $this->pending($this->manager, 3, '假單A');
        $this->pending($this->manager, 5, '假單B');

        $this->artisan('eip:remind-pending-approvals')->expectsOutputToContain('已提醒 1 位')->assertSuccessful();

        $this->assertCount(1, $this->reminders($this->manager));
        $data = $this->reminders($this->manager)->first()->data;
        $this->assertStringContainsString('2 張', $data['title']);
        $this->assertStringContainsString('假單A', $data['message']);
        $this->assertStringContainsString('假單B', $data['message']);
        $this->assertSame(route('approvals.index'), $data['action_url']);
        Mail::assertQueued(EipNotificationMail::class, fn ($m) => $m->hasTo($this->manager->email));
    }

    public function test_recent_records_are_not_reminded(): void
    {
        $this->pending($this->manager, 1);

        $this->artisan('eip:remind-pending-approvals')->expectsOutputToContain('已提醒 0 位');
        $this->assertCount(0, $this->reminders($this->manager));
    }

    public function test_days_option_is_respected(): void
    {
        $this->pending($this->manager, 1);

        $this->artisan('eip:remind-pending-approvals', ['--days' => 1])->expectsOutputToContain('已提醒 1 位');
    }

    public function test_only_one_reminder_per_day(): void
    {
        $this->pending($this->manager, 4);

        $this->artisan('eip:remind-pending-approvals');
        $this->artisan('eip:remind-pending-approvals')->expectsOutputToContain('已提醒 0 位');

        $this->assertCount(1, $this->reminders($this->manager));
    }

    public function test_finished_withdrawn_or_inactive_cases_are_skipped(): void
    {
        $this->pending($this->manager, 6, '已核准的', 'approved', 'approved');
        $this->pending($this->manager, 6, '已撤回的', 'withdrawn', 'pending');
        $resigned = User::factory()->create(['role' => 'manager', 'status' => 'resigned']);
        $this->pending($resigned, 6, '離職主管的');

        $this->artisan('eip:remind-pending-approvals')->expectsOutputToContain('已提醒 0 位');
        $this->assertCount(0, $this->reminders($this->manager));
        $this->assertCount(0, $this->reminders($resigned));
    }

    public function test_long_lists_are_truncated(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->pending($this->manager, 3, "單據$i");
        }

        $this->artisan('eip:remind-pending-approvals');

        $msg = $this->reminders($this->manager)->first()->data['message'];
        $this->assertStringContainsString('另有 2 張', $msg);
        $this->assertSame(5, substr_count($msg, '・'));
    }

    public function test_command_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command, 'eip:remind-pending-approvals'));

        $this->assertCount(1, $events);
        $this->assertSame('0 9 * * *', $events->first()->expression);
    }
}
