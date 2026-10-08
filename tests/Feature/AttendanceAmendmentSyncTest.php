<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAmendmentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $employee;
    protected Department $department;
    protected Form $clockAdjustForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '資訊工程部',
            'code' => 'IT',
        ]);

        $this->manager = User::factory()->create([
            'name' => '資訊主管',
            'role' => 'manager',
            'department_id' => $this->department->id,
        ]);

        $this->employee = User::factory()->create([
            'name' => '工程師小陳',
            'role' => 'employee',
            'department_id' => $this->department->id,
        ]);

        $this->department->update(['leader_id' => $this->manager->id]);

        $this->clockAdjustForm = Form::create([
            'name' => '忘刷 / 補打卡申請單',
            'code' => 'CLOCK_ADJUST',
            'description' => '員工因公外出、刷卡機異常或忘記刷卡之補登申請',
            'is_active' => true,
            'fields_schema' => [
                ['key' => 'adjust_date', 'label' => '補刷日期', 'type' => 'date', 'required' => true],
                ['key' => 'adjust_type', 'label' => '補刷類型', 'type' => 'select', 'options' => ['上班卡', '下班卡', '全日 (上下班)'], 'required' => true],
                ['key' => 'actual_time', 'label' => '實際出勤時間', 'type' => 'text', 'placeholder' => '如 09:00 或 09:00 - 18:00', 'required' => true],
                ['key' => 'reason', 'label' => '補刷原因說明', 'type' => 'textarea', 'required' => true],
            ],
        ]);
    }

    public function test_attendance_index_provides_clock_adjust_form_id(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Attendance/Index')
            ->where('clockAdjustFormId', $this->clockAdjustForm->id)
        );
    }

    public function test_form_create_page_accepts_prefill_query_parameters(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('forms.create', [
                'form' => $this->clockAdjustForm->id,
                'prefill' => 1,
                'date' => '2026-10-07',
                'adjust_type' => '下班卡',
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Forms/Create')
            ->where('prefillData.adjust_date', '2026-10-07')
            ->where('prefillData.adjust_type', '下班卡')
        );
    }

    public function test_clock_adjust_approval_automatically_creates_and_syncs_attendance_for_empty_day(): void
    {
        $targetDate = '2026-10-05';

        // 確保當天完全沒有考勤紀錄
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->employee->id,
            'date' => $targetDate,
        ]);

        // 小陳送出全日補打卡申請
        $this->actingAs($this->employee)
            ->post(route('forms.store', $this->clockAdjustForm->id), [
                'title' => '2026-10-05 外派客戶端忘刷補登',
                'data' => [
                    'adjust_date' => $targetDate,
                    'adjust_type' => '全日 (上下班)',
                    'actual_time' => '09:00 - 18:00',
                    'reason' => '全日至客戶現場部署伺服器，無法至辦公室刷卡',
                ],
            ]);

        $formRequest = FormRequest::where('user_id', $this->employee->id)
            ->where('form_id', $this->clockAdjustForm->id)
            ->first();

        $this->assertNotNull($formRequest);
        $pendingRecord = $formRequest->approvalRecords()->where('status', 'pending')->first();

        // 主管核准並結案
        $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '外派屬實，予以核准補登考勤',
            ]);

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);

        // 驗證考勤紀錄已被自動生成並同步補齊
        $attendance = Attendance::where('user_id', $this->employee->id)
            ->whereDate('date', $targetDate)
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('09:00', Carbon::parse($attendance->clock_in_at)->format('H:i'));
        $this->assertEquals('18:00', Carbon::parse($attendance->clock_out_at)->format('H:i'));
        $this->assertEquals('normal', $attendance->status);
        $this->assertGreaterThanOrEqual(8.0, (float) $attendance->work_hours);
        $this->assertStringContainsString('單號 #' . $formRequest->id, $attendance->note);

        // 驗證審計留痕
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance_amendment_synced',
            'auditable_type' => Attendance::class,
            'auditable_id' => $attendance->id,
        ]);
    }

    public function test_clock_adjust_approval_corrects_missing_clock_out_record(): void
    {
        $targetDate = '2026-10-06';

        // 建立當天只有早上上班打卡（狀態可能為 late 或缺下班卡）
        $attendance = Attendance::create([
            'user_id' => $this->employee->id,
            'date' => $targetDate,
            'clock_in_at' => Carbon::parse($targetDate . ' 08:55:00'),
            'clock_out_at' => null,
            'status' => 'late',
            'clock_in_type' => 'office',
        ]);

        // 小陳提報下班補打卡單
        $this->actingAs($this->employee)
            ->post(route('forms.store', $this->clockAdjustForm->id), [
                'title' => '2026-10-06 晚上急於下班漏刷',
                'data' => [
                    'adjust_date' => $targetDate,
                    'adjust_type' => '下班卡',
                    'actual_time' => '18:30',
                    'reason' => '下班時趕班車忘記打卡',
                ],
            ]);

        $formRequest = FormRequest::where('user_id', $this->employee->id)
            ->where('form_id', $this->clockAdjustForm->id)
            ->latest()
            ->first();

        $this->assertNotNull($formRequest);

        // 主管核准
        $actionResponse = $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '准予補登下班卡',
            ]);

        $actionResponse->assertRedirect();
        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);

        $attendance->refresh();

        // 原上班時間保留
        $this->assertEquals('08:55', Carbon::parse($attendance->clock_in_at)->format('H:i'));
        // 下班時間成功補齊
        $this->assertEquals('18:30', Carbon::parse($attendance->clock_out_at)->format('H:i'));
        // 狀態校正為正常出勤
        $this->assertEquals('normal', $attendance->status);
        $this->assertGreaterThanOrEqual(8.0, (float) $attendance->work_hours);
        $this->assertStringContainsString('單號 #' . $formRequest->id, $attendance->note);
    }
}
