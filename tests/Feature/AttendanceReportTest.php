<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    public function test_hr_and_admin_can_view_attendance_reports(): void
    {
        $hr = User::where('email', 'hr@eip.local')->first();
        $admin = User::where('email', 'admin@eip.local')->first();

        $hrResponse = $this->actingAs($hr)->get('/attendance/reports');
        $hrResponse->assertStatus(200);
        $hrResponse->assertInertia(fn ($page) => $page->component('Attendance/Report')
            ->has('userReports')
            ->has('summaryStats')
            ->has('departments')
            ->where('canExport', true)
        );

        $adminResponse = $this->actingAs($admin)->get('/attendance/reports');
        $adminResponse->assertStatus(200);
    }

    public function test_manager_can_view_attendance_reports(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();

        $response = $this->actingAs($manager)->get('/attendance/reports');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Attendance/Report'));
    }

    public function test_regular_employee_cannot_view_attendance_reports(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();

        $response = $this->actingAs($employee)->get('/attendance/reports');
        $response->assertStatus(403);
    }

    public function test_can_filter_attendance_reports_by_month_and_department(): void
    {
        $hr = User::where('email', 'hr@eip.local')->first();
        $dept = Department::first();

        $response = $this->actingAs($hr)->get("/attendance/reports?month=2026-10&department_id={$dept->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Attendance/Report')
            ->where('filters.month', '2026-10')
            ->where('filters.department_id', (string) $dept->id)
        );
    }

    public function test_hr_can_export_summary_csv(): void
    {
        $hr = User::where('email', 'hr@eip.local')->first();

        $response = $this->actingAs($hr)->get('/attendance/reports/export-summary?month=2026-10');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // 串流輸出內容檢查
        $content = $response->streamedContent();

        // 檢查 UTF-8 BOM 與表頭
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $this->assertStringStartsWith($bom, $content);
        $this->assertStringContainsString('員工工號,員工姓名,部門,職稱,出勤天數,累計工時(小時),遲到次數,早退次數', $content);

        // 檢查審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'export_attendance_summary',
            'user_id' => $hr->id,
        ]);
    }

    public function test_hr_can_export_details_csv(): void
    {
        $hr = User::where('email', 'hr@eip.local')->first();
        $employee = User::where('email', 'employee@eip.local')->first();

        // 建立或更新一筆測試出勤紀錄 (避免與 Seeder 衝突)
        Attendance::updateOrCreate(
            ['user_id' => $employee->id, 'date' => '2026-10-15'],
            [
                'clock_in_at' => '2026-10-15 09:05:00',
                'clock_out_at' => '2026-10-15 18:05:00',
                'status' => 'normal',
                'work_hours' => 9.0,
                'note' => '準時上下班',
            ]
        );

        $response = $this->actingAs($hr)->get('/attendance/reports/export-details?month=2026-10');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $this->assertStringStartsWith($bom, $content);
        $this->assertStringContainsString('日期,員工工號,姓名,部門,上班打卡時間,下班打卡時間,當日工時,狀態,打卡備註', $content);
        $this->assertStringContainsString($employee->name, $content);
        $this->assertStringContainsString('準時上下班', $content);

        // 檢查審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'export_attendance_details',
            'user_id' => $hr->id,
        ]);
    }
}
