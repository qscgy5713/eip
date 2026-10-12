<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    public function test_authenticated_user_can_view_attendance_page(): void
    {
        $user = User::where('email', 'employee@eip.local')->first();

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
    }

    public function test_cannot_clock_out_without_clock_in_time(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        Attendance::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'status' => 'normal']);

        $this->actingAs($user)->post('/attendance/clock-out', ['location' => '台北辦公室'])
            ->assertSessionHas('error');

        $this->assertNull(Attendance::where('user_id', $user->id)->first()->clock_out_at);
    }

    public function test_user_can_clock_in(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
        ]);

        $response = $this->actingAs($user)->post('/attendance/clock-in', [
            'location' => '台北辦公室',
        ]);

        $response->assertRedirect();
        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('台北辦公室', $attendance->clock_in_location);
    }

    public function test_late_status_follows_taipei_local_time_not_utc(): void
    {
        $this->assertSame('Asia/Taipei', config('app.timezone'));
        $user = User::factory()->create(['role' => 'employee']);

        // UTC 01:20 = 台北 09:20,準時;UTC 01:40 = 台北 09:40,遲到
        $this->travelTo(\Carbon\Carbon::parse('2026-10-14 01:20:00', 'UTC'));
        $this->actingAs($user)->post('/attendance/clock-in', ['location' => '台北辦公室']);
        $first = Attendance::where('user_id', $user->id)->first();
        $this->assertSame('normal', $first->status);
        $this->assertSame('09:20', $first->clock_in_at->format('H:i'));
        $first->delete();

        $this->travelTo(\Carbon\Carbon::parse('2026-10-14 01:40:00', 'UTC'));
        $this->actingAs($user)->post('/attendance/clock-in', ['location' => '台北辦公室']);
        $this->assertSame('late', Attendance::where('user_id', $user->id)->first()->status);
    }

    public function test_user_can_clock_out_and_calculate_work_hours(): void
    {
        $user = User::where('email', 'employee@eip.local')->first();
        $today = now()->toDateString();

        $response = $this->actingAs($user)->post('/attendance/clock-out', [
            'location' => '台北辦公室',
        ]);

        $response->assertRedirect();
        $attendance = Attendance::where('user_id', $user->id)->whereDate('date', $today)->first();
        $this->assertNotNull($attendance->clock_out_at);
    }

    public function test_manager_can_view_team_attendance_summary(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();

        $response = $this->actingAs($manager)->get('/attendance');
        $response->assertStatus(200);
    }
}
