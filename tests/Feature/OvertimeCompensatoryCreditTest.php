<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeCompensatoryCreditTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $hr;
    protected User $employee;
    protected Department $department;
    protected Form $overtimeForm;
    protected Form $leaveForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '雲端架構部',
            'code' => 'CLOUD',
        ]);

        $this->manager = User::factory()->create([
            'name' => '雲端主管',
            'role' => 'manager',
            'department_id' => $this->department->id,
        ]);

        $this->hr = User::factory()->create([
            'name' => '人資主管',
            'role' => 'hr',
            'department_id' => $this->department->id,
        ]);

        $this->employee = User::factory()->create([
            'name' => '架構師阿明',
            'role' => 'employee',
            'department_id' => $this->department->id,
        ]);

        $this->department->update(['leader_id' => $this->manager->id]);

        $this->overtimeForm = Form::create([
            'name' => '加班申請單',
            'code' => 'OVERTIME',
            'description' => '平日延長工時或國定休假日專案支援加班申請',
            'is_active' => true,
            'fields_schema' => [
                ['key' => 'overtime_type', 'label' => '加班類型', 'type' => 'select', 'options' => ['平日延長工時', '週末假日加班', '國定假日專案支援']],
                ['key' => 'overtime_date', 'label' => '加班日期', 'type' => 'date'],
                ['key' => 'hours', 'label' => '加班時數 (小時)', 'type' => 'number'],
                ['key' => 'compensation', 'label' => '補償方式', 'type' => 'select', 'options' => ['換取補休時數', '核發加班費']],
                ['key' => 'reason', 'label' => '專案事由與工作內容', 'type' => 'textarea'],
            ],
        ]);

        $this->leaveForm = Form::create([
            'name' => '休假申請單',
            'code' => 'LEAVE',
            'description' => '請假申請',
            'is_active' => true,
            'fields_schema' => [
                ['key' => 'leave_type', 'label' => '假別', 'type' => 'select', 'options' => ['特休假', '補休', '事假', '病假']],
                ['key' => 'start_date', 'label' => '開始日期', 'type' => 'date'],
                ['key' => 'end_date', 'label' => '結束日期', 'type' => 'date'],
                ['key' => 'days', 'label' => '請假天數', 'type' => 'number'],
                ['key' => 'reason', 'label' => '請假事由', 'type' => 'textarea'],
            ],
        ]);
    }

    public function test_overtime_request_with_compensatory_credits_leave_balance_upon_approval(): void
    {
        $year = (int) date('Y');

        // 初始時補休額度為 0
        $balance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('year', $year)
            ->where('leave_type', 'compensatory')
            ->first();
        $initialDays = $balance ? (float) $balance->allocated_days : 0.0;
        $this->assertEquals(0.0, $initialDays);

        // 阿明申請加班 8 小時（達 8 小時門檻，將觸發直屬主管初審 + 人資主管工時稽核兩級簽核），選擇「換取補休時數」
        $this->actingAs($this->employee)
            ->post(route('forms.store', $this->overtimeForm->id), [
                'title' => '雲端架構大夜上線加班',
                'data' => [
                    'overtime_type' => '平日延長工時',
                    'overtime_date' => date('Y-m-d'),
                    'hours' => 8.0,
                    'compensation' => '換取補休時數',
                    'reason' => '核心資料庫線上不停機升級遷移',
                ],
            ]);

        $formRequest = FormRequest::where('user_id', $this->employee->id)
            ->where('form_id', $this->overtimeForm->id)
            ->first();

        $this->assertNotNull($formRequest);
        $this->assertEquals(2, $formRequest->total_steps);

        // 1. 直屬主管初審核准
        $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '專案上線辛勞，主管初審通過',
            ]);

        $formRequest->refresh();
        $this->assertEquals('pending', $formRequest->status);
        $this->assertEquals(2, $formRequest->current_step);

        // 2. 人資主管工時稽核二審核准並結案
        $this->actingAs($this->hr)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '工時稽核無誤，予以結案並折算補休',
            ]);

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);

        // 驗證補休額度已自動折算 8/8 = 1.0 天入帳
        $compBalance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('year', $year)
            ->where('leave_type', 'compensatory')
            ->first();

        $this->assertNotNull($compBalance);
        $this->assertEquals(1.0, (float) $compBalance->allocated_days);
        $this->assertEquals(1.0, (float) $compBalance->available_days);
        $this->assertStringContainsString('加班單 #' . $formRequest->id, $compBalance->note);

        // 驗證審計留痕
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'overtime_compensatory_credited',
            'auditable_type' => LeaveBalance::class,
            'auditable_id' => $compBalance->id,
        ]);
    }

    public function test_overtime_request_with_salary_compensation_does_not_credit_compensatory_leave(): void
    {
        $year = (int) date('Y');

        // 阿明申請加班 4 小時（單關主管審核），選擇「核發加班費」
        $this->actingAs($this->employee)
            ->post(route('forms.store', $this->overtimeForm->id), [
                'title' => '伺服器巡檢加班',
                'data' => [
                    'overtime_type' => '週末假日加班',
                    'overtime_date' => date('Y-m-d'),
                    'hours' => 4.0,
                    'compensation' => '核發加班費',
                    'reason' => '假日例行機房機櫃巡檢',
                ],
            ]);

        $formRequest = FormRequest::where('user_id', $this->employee->id)
            ->where('form_id', $this->overtimeForm->id)
            ->first();

        $this->assertEquals(1, $formRequest->total_steps);

        // 主管核准
        $this->actingAs($this->manager)
            ->post(route('forms.action', $formRequest->id), [
                'status' => 'approved',
                'comment' => '准予核發加班費',
            ]);

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);

        // 驗證補休額度未增加
        $compBalance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('year', $year)
            ->where('leave_type', 'compensatory')
            ->first();

        $this->assertEquals(0.0, (float) ($compBalance?->allocated_days ?? 0.0));
    }

    public function test_credited_compensatory_leave_can_be_applied_and_held(): void
    {
        // 先給同仁 4 小時加班折算 0.5 天補休
        $this->actingAs($this->employee)
            ->post(route('forms.store', $this->overtimeForm->id), [
                'title' => '臨時支援加班',
                'data' => [
                    'overtime_type' => '平日延長工時',
                    'overtime_date' => date('Y-m-d'),
                    'hours' => 4.0,
                    'compensation' => '換取補休時數',
                    'reason' => '緊急資安告警排查',
                ],
            ]);

        $otRequest = FormRequest::where('user_id', $this->employee->id)->latest()->first();

        $this->actingAs($this->manager)
            ->post(route('forms.action', $otRequest->id), [
                'status' => 'approved',
                'comment' => '核准補休',
            ]);

        // 確認補休目前可用 0.5 天
        $year = (int) date('Y');
        $compBalance = LeaveBalance::where('user_id', $this->employee->id)
            ->where('year', $year)
            ->where('leave_type', 'compensatory')
            ->first();

        $this->assertEquals(0.5, (float) $compBalance->available_days);

        // 同仁申請 0.5 天補休
        $response = $this->actingAs($this->employee)
            ->post(route('forms.store', $this->leaveForm->id), [
                'title' => '使用補休半天',
                'data' => [
                    'leave_type' => '補休',
                    'start_date' => date('Y-m-d', strtotime('+1 day')),
                    'end_date' => date('Y-m-d', strtotime('+1 day')),
                    'days' => 0.5,
                    'reason' => '休息充電',
                ],
            ]);

        $response->assertRedirect();
        $compBalance->refresh();
        $this->assertEquals(0.5, (float) $compBalance->pending_days);
        $this->assertEquals(0.0, (float) $compBalance->available_days);
    }

    public function test_leave_balances_index_provides_overtime_requests_and_form_id(): void
    {
        // 建立一筆加班單
        $this->actingAs($this->employee)
            ->post(route('forms.store', $this->overtimeForm->id), [
                'title' => '專案上線加班',
                'data' => [
                    'overtime_type' => '平日延長工時',
                    'overtime_date' => '2026-10-08',
                    'hours' => 6.0,
                    'compensation' => '換取補休時數',
                    'reason' => 'API 壓力測試支援',
                ],
            ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-balances.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('LeaveBalances/Index')
            ->has('myOvertimeRequests', 1)
            ->where('overtimeFormId', $this->overtimeForm->id)
            ->where('myOvertimeRequests.0.title', '專案上線加班')
            ->where('myOvertimeRequests.0.hours', 6)
            ->where('myOvertimeRequests.0.credit_days', 0.75)
            ->where('myOvertimeRequests.0.compensation', '換取補休時數')
        );
    }
}
