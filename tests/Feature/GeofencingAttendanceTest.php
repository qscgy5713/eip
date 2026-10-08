<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofencingAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $this->employee = User::where('email', 'employee@eip.local')->first();
        $this->manager = User::where('email', 'manager@eip.local')->first();

        // 清除 seeder 預置之今日打卡資料以供各測試獨立執行
        Attendance::where('user_id', $this->employee->id)->whereDate('date', now()->toDateString())->delete();
    }

    public function test_clock_in_within_geofence_marks_as_office(): void
    {
        // 總部座標 25.033964, 121.564468 (半徑 500m)
        // 測試打卡座標：非常接近總部 (約 15 公尺)
        $lat = 25.034050;
        $lng = 121.564520;

        $response = $this->actingAs($this->employee)->post(route('attendance.clockIn'), [
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        $response->assertRedirect();

        $today = now()->toDateString();
        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', $today)->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('office', $attendance->clock_in_type);
        $this->assertNotNull($attendance->clock_in_distance);
        $this->assertLessThanOrEqual(500, $attendance->clock_in_distance);
        $this->assertStringContainsString('圍欄內', $attendance->clock_in_location);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'clock_in',
            'auditable_id' => $attendance->id,
        ]);
    }

    public function test_clock_in_outside_geofence_marks_as_remote_with_note(): void
    {
        // 測試打卡座標：距離總部約 2 公里外
        $lat = 25.050000;
        $lng = 121.580000;
        $note = '客戶展示會議 (內湖軟體園區)';

        $response = $this->actingAs($this->employee)->post(route('attendance.clockIn'), [
            'latitude' => $lat,
            'longitude' => $lng,
            'field_work_note' => $note,
        ]);

        $response->assertRedirect();

        $today = now()->toDateString();
        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', $today)->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('remote', $attendance->clock_in_type);
        $this->assertGreaterThan(500, $attendance->clock_in_distance);
        $this->assertEquals($note, $attendance->field_work_note);
        $this->assertStringContainsString('外勤/遠端', $attendance->clock_in_location);
    }

    public function test_clock_in_without_coordinates_falls_back_to_unverified(): void
    {
        $response = $this->actingAs($this->employee)->post(route('attendance.clockIn'), []);

        $response->assertRedirect();

        $today = now()->toDateString();
        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', $today)->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('unverified', $attendance->clock_in_type);
        $this->assertNull($attendance->clock_in_distance);
    }

    public function test_clock_out_with_geofence_calculates_properly(): void
    {
        // 先建立上班紀錄
        $this->actingAs($this->employee)->post(route('attendance.clockIn'), [
            'latitude' => 25.033964,
            'longitude' => 121.564468,
        ]);

        // 下班打卡於辦公室
        $response = $this->actingAs($this->employee)->post(route('attendance.clockOut'), [
            'latitude' => 25.033980,
            'longitude' => 121.564490,
        ]);

        $response->assertRedirect();

        $today = now()->toDateString();
        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', $today)->first();

        $this->assertNotNull($attendance->clock_out_at);
        $this->assertEquals('office', $attendance->clock_out_type);
        $this->assertLessThanOrEqual(500, $attendance->clock_out_distance);
    }

    public function test_attendance_index_provides_geofence_config_and_team_type(): void
    {
        $response = $this->actingAs($this->manager)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Attendance/Index')
                ->has('geofenceConfig.lat')
                ->has('geofenceConfig.lng')
                ->has('geofenceConfig.radius')
                ->has('geofenceConfig.name')
        );
    }
}
