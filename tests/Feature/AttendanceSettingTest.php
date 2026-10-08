<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $hr;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@eip.local')->first();
        $this->hr = User::where('role', 'hr')->first() ?? User::factory()->create(['role' => 'hr']);
        $this->employee = User::where('email', 'employee@eip.local')->first();

        // 清理當日既有打卡資料，避免唯一性衝突
        Attendance::where('user_id', $this->employee->id)->whereDate('date', now()->toDateString())->delete();
    }

    public function test_admin_and_hr_can_view_settings_page(): void
    {
        $responseAdmin = $this->actingAs($this->admin)->get('/attendance/settings');
        $responseAdmin->assertStatus(200);

        $responseHr = $this->actingAs($this->hr)->get('/attendance/settings');
        $responseHr->assertStatus(200);
    }

    public function test_regular_employee_cannot_access_settings(): void
    {
        $response = $this->actingAs($this->employee)->get('/attendance/settings');
        $response->assertStatus(403);
    }

    public function test_admin_can_update_office_address_and_radius(): void
    {
        $response = $this->actingAs($this->admin)->post('/attendance/settings', [
            'office_name' => '新板特區辦公大樓',
            'office_address' => '新北市板橋區中山路一段161號',
            'office_lat' => 25.013534,
            'office_lng' => 121.465432,
            'allowed_radius' => 250,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('新板特區辦公大樓', SystemSetting::get('geofence_office_name'));
        $this->assertEquals('新北市板橋區中山路一段161號', SystemSetting::get('geofence_office_address'));
        $this->assertEquals('250', SystemSetting::get('geofence_allowed_radius'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update_geofence_setting',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_clock_in_outside_radius_without_reason_is_rejected(): void
    {
        // 座標設定在公司總部數公里外（例如板橋車站 25.013534, 121.465432，而總部在信義區）
        $response = $this->actingAs($this->employee)->post('/attendance/clock-in', [
            'latitude' => 25.013534,
            'longitude' => 121.465432,
            'field_work_note' => '', // 未填事由
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // 確認資料庫中不可有當日打卡紀錄
        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', now()->toDateString())->first();
        $this->assertNull($attendance);
    }

    public function test_clock_in_outside_radius_with_reason_is_accepted(): void
    {
        $response = $this->actingAs($this->employee)->post('/attendance/clock-in', [
            'latitude' => 25.013534,
            'longitude' => 121.465432,
            'field_work_note' => '拜訪板橋客戶展示產品系統',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', now()->toDateString())->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('remote', $attendance->clock_in_type);
        $this->assertEquals('拜訪板橋客戶展示產品系統', $attendance->field_work_note);
    }

    public function test_clock_out_outside_radius_without_reason_is_rejected(): void
    {
        // 先在辦公室內上班打卡
        $this->actingAs($this->employee)->post('/attendance/clock-in', [
            'latitude' => 25.033964,
            'longitude' => 121.564468,
        ]);

        // 下班時跑到外縣市，且未填寫外勤事由
        $response = $this->actingAs($this->employee)->post('/attendance/clock-out', [
            'latitude' => 25.013534,
            'longitude' => 121.465432,
            'field_work_note' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', now()->toDateString())->first();
        $this->assertNull($attendance->clock_out_at);
    }

    public function test_unconfigured_office_location_defaults_to_remote_without_reason_required(): void
    {
        // 管理員清空公司座標（切換為全遠端模式）
        $response = $this->actingAs($this->admin)->post('/attendance/settings', [
            'office_name' => '全遠端虛擬總部',
            'office_address' => '',
            'office_lat' => null,
            'office_lng' => null,
            'allowed_radius' => null,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 同仁在任意地點打卡，未填寫事由，應直接成功且判定為 remote
        $responseClockIn = $this->actingAs($this->employee)->post('/attendance/clock-in', [
            'latitude' => 24.123456,
            'longitude' => 120.654321,
            'field_work_note' => '', // 未填事由
        ]);

        $responseClockIn->assertRedirect();
        $responseClockIn->assertSessionHas('success');

        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', now()->toDateString())->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('remote', $attendance->clock_in_type);
        $this->assertStringContainsString('遠端辦公', $attendance->clock_in_location);
    }
}
