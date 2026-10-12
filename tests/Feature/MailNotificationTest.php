<?php

namespace Tests\Feature;

use App\Mail\EipNotificationMail;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    private function notify(User $user, string $title = '簽核結果通知'): void
    {
        $user->notify(new EipSystemNotification(
            title: $title, message: '您的請假單已核准', type: 'form_approved',
            actionUrl: 'http://localhost/forms/1', senderName: '張主管',
        ));
    }

    public function test_notification_is_also_queued_as_email(): void
    {
        Mail::fake();
        $user = User::where('email', 'employee@eip.local')->first();
        $before = $user->notifications()->count();

        $this->notify($user);

        Mail::assertQueued(EipNotificationMail::class, fn ($m) => $m->hasTo('employee@eip.local') && $m->title === '簽核結果通知');
        $this->assertSame($before + 1, $user->notifications()->count(), '站內通知仍照常寫入');
    }

    public function test_user_can_opt_out_of_email(): void
    {
        Mail::fake();
        $user = User::where('email', 'employee@eip.local')->first();
        $user->update(['mail_notifications' => false]);
        $before = $user->notifications()->count();

        $this->notify($user);

        Mail::assertNothingQueued();
        $this->assertSame($before + 1, $user->notifications()->count());
    }

    public function test_global_switch_disables_email(): void
    {
        Mail::fake();
        config(['eip.mail_notifications' => false]);

        $this->notify(User::where('email', 'employee@eip.local')->first());

        Mail::assertNothingQueued();
    }

    public function test_mail_failure_does_not_break_the_notification(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $user = User::where('email', 'employee@eip.local')->first();
        $before = $user->notifications()->count();

        $this->notify($user);

        $this->assertSame($before + 1, $user->notifications()->count());
    }

    public function test_mail_renders_in_chinese_with_subject_and_link(): void
    {
        $mail = new EipNotificationMail('簽核結果通知', '您的請假單已核准', 'http://localhost/forms/1', '張主管', '林同仁');

        $mail->assertHasSubject('['.\App\Services\Branding::name().'] 簽核結果通知');
        $mail->assertSeeInHtml('您的請假單已核准');
        $mail->assertSeeInHtml('林同仁 您好');
        $mail->assertSeeInHtml('http://localhost/forms/1');
        $mail->assertSeeInHtml('張主管');
    }

    public function test_html_in_message_is_escaped(): void
    {
        $mail = new EipNotificationMail('標題', '<script>alert(1)</script>', null, null, '');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $mail->render());
    }

    public function test_real_workflow_sends_email_to_approver(): void
    {
        Mail::fake();
        $employee = User::where('email', 'employee@eip.local')->first();
        $form = \App\Models\Form::where('code', 'LEAVE')->first();

        $this->actingAs($employee)->post(route('forms.store', $form->id), [
            'title' => '事假', 'data' => ['leave_type' => '事假', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'days' => 1, 'reason' => '私事'],
        ])->assertSessionHasNoErrors();

        Mail::assertQueued(EipNotificationMail::class);
    }

    public function test_preference_endpoint(): void
    {
        $user = User::where('email', 'employee@eip.local')->first();

        $this->actingAs($user)->put(route('profile.notification-preferences'), ['mail_notifications' => false])->assertRedirect(route('profile.edit'));
        $this->assertFalse($user->fresh()->mail_notifications);

        $this->actingAs($user)->put(route('profile.notification-preferences'), ['mail_notifications' => true]);
        $this->assertTrue($user->fresh()->mail_notifications);

        $this->actingAs($user)->put(route('profile.notification-preferences'), ['mail_notifications' => 'abc'])->assertSessionHasErrors('mail_notifications');
        $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($p) => $p->where('mailNotifications', true)->where('mailNotificationsAvailable', true));
    }
}
