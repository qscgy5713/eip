<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    private function save(User $u, array $over = [])
    {
        return $this->actingAs($u)->post(route('attendance.settings.update'), array_merge([
            'office_name' => '公司', 'office_address' => '', 'office_lat' => 25.05, 'office_lng' => 121.55, 'allowed_radius' => 300,
            'work_start' => '08:30', 'work_end' => '17:30', 'work_late_grace_minutes' => 10, 'work_required_hours' => 8,
        ], $over));
    }

    private function statusFor(string $in, string $out = '18:30'): string
    {
        $a = new Attendance(['clock_in_at' => Carbon::parse("2026-10-13 $in"), 'clock_out_at' => Carbon::parse("2026-10-13 $out")]);
        $a->calculateWorkHours();

        return $a->status;
    }

    public function test_defaults_keep_previous_behaviour(): void
    {
        $this->assertSame('normal', $this->statusFor('09:30', '18:30'));
        $this->assertSame('late', $this->statusFor('09:31', '18:30'));
        $this->assertSame('early_leave', $this->statusFor('09:00', '16:00'));
    }

    public function test_hr_can_change_schedule_and_it_affects_late_rule(): void
    {
        $this->save(User::where('email', 'hr@eip.local')->first())->assertSessionHasNoErrors();

        $this->assertSame('normal', $this->statusFor('08:40', '17:40'));
        $this->assertSame('late', $this->statusFor('08:41', '17:41'));
    }

    public function test_required_hours_change_affects_early_leave(): void
    {
        $this->save(User::where('email', 'admin@eip.local')->first(), ['work_required_hours' => 6])->assertSessionHasNoErrors();

        $this->assertSame('normal', $this->statusFor('08:30', '14:40'));
        $this->assertSame('early_leave', $this->statusFor('08:30', '14:00'));
    }

    public function test_employee_cannot_change_schedule(): void
    {
        $this->save(User::where('email', 'employee@eip.local')->first())->assertForbidden();

        $this->assertNull(SystemSetting::get('work_start'));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $hr = User::where('email', 'hr@eip.local')->first();

        $this->save($hr, ['work_start' => '25:00'])->assertSessionHasErrors('work_start');
        $this->save($hr, ['work_start' => '18:00', 'work_end' => '09:00'])->assertSessionHasErrors('work_end');
        $this->save($hr, ['work_late_grace_minutes' => 999])->assertSessionHasErrors('work_late_grace_minutes');
        $this->save($hr, ['work_required_hours' => 0])->assertSessionHasErrors('work_required_hours');
        $this->assertNull(SystemSetting::get('work_start'));
    }

    public function test_saving_geofence_only_leaves_schedule_untouched(): void
    {
        $this->actingAs(User::where('email', 'hr@eip.local')->first())->post(route('attendance.settings.update'), ['office_name' => '公司', 'office_lat' => 25.05, 'office_lng' => 121.55, 'allowed_radius' => 300])->assertSessionHasNoErrors();

        $this->assertNull(SystemSetting::get('work_start'));
    }

    public function test_settings_page_receives_schedule(): void
    {
        $this->actingAs(User::where('email', 'hr@eip.local')->first())->get(route('attendance.settings'))
            ->assertInertia(fn ($p) => $p->where('schedule.start', '09:00')->where('schedule.grace', 30)->where('schedule.required_hours', 8));
    }

    public function test_clock_in_uses_configured_threshold(): void
    {
        $this->save(User::where('email', 'admin@eip.local')->first(), ['work_start' => '10:00', 'work_end' => '19:00', 'work_late_grace_minutes' => 0]);
        $u = User::where('email', 'employee@eip.local')->first();
        Attendance::where('user_id', $u->id)->delete();

        $this->travelTo(Carbon::parse('2026-10-13 09:50'));
        $this->actingAs($u)->post(route('attendance.clockIn'), []);

        $this->assertSame('normal', Attendance::where('user_id', $u->id)->first()->status);
    }
}
