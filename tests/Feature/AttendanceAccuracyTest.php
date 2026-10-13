<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        SystemSetting::set('geofence_office_lat', '25.0513505');
        SystemSetting::set('geofence_office_lng', '121.5556057');
        SystemSetting::set('geofence_allowed_radius', '300');
        $this->u = User::where('email', 'employee@eip.local')->firstOrFail();
        Attendance::where('user_id', $this->u->id)->whereDate('date', now()->toDateString())->delete();
    }

    public function test_clock_in_stores_reported_accuracy(): void
    {
        $this->actingAs($this->u)->post(route('attendance.clockIn'), ['latitude' => 25.0513505, 'longitude' => 121.5556057, 'accuracy' => 37.6]);

        $att = Attendance::where('user_id', $this->u->id)->whereDate('date', now()->toDateString())->firstOrFail();
        $this->assertSame(38, $att->clock_in_accuracy);
        $this->assertSame('office', $att->clock_in_type);
    }

    public function test_clock_out_stores_accuracy(): void
    {
        $this->actingAs($this->u)->post(route('attendance.clockIn'), ['latitude' => 25.0513505, 'longitude' => 121.5556057, 'accuracy' => 20]);
        $this->actingAs($this->u)->post(route('attendance.clockOut'), ['latitude' => 25.0513505, 'longitude' => 121.5556057, 'accuracy' => 55]);

        $att = Attendance::where('user_id', $this->u->id)->whereDate('date', now()->toDateString())->firstOrFail();
        $this->assertSame(55, $att->clock_out_accuracy);
    }

    public function test_garbage_accuracy_is_ignored_not_an_error(): void
    {
        foreach (['abc', '-5', '99999999', ['x']] as $bad) {
            Attendance::where('user_id', $this->u->id)->delete();
            $this->actingAs($this->u)->post(route('attendance.clockIn'), ['latitude' => 25.0513505, 'longitude' => 121.5556057, 'accuracy' => $bad])->assertSessionHasNoErrors();

            $att = Attendance::where('user_id', $this->u->id)->firstOrFail();
            $this->assertNull($att->clock_in_accuracy, 'accuracy=' . json_encode($bad));
        }
    }

    public function test_missing_accuracy_still_clocks_in(): void
    {
        $this->actingAs($this->u)->post(route('attendance.clockIn'), ['latitude' => 25.0513505, 'longitude' => 121.5556057])->assertSessionHas('success');

        $this->assertNull(Attendance::where('user_id', $this->u->id)->firstOrFail()->clock_in_accuracy);
    }
}
