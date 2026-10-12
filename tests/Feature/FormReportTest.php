<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormReportTest extends TestCase
{
    use RefreshDatabase;

    private Department $rd;
    private Department $other;
    private User $rdEmp;
    private User $otherEmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        FormRequest::query()->delete();

        $this->rd = Department::create(['name' => '報表測試A部', 'code' => 'RPTA']);
        $this->other = Department::create(['name' => '報表測試B部', 'code' => 'RPTB']);
        $this->rdEmp = User::factory()->create(['department_id' => $this->rd->id, 'role' => 'employee', 'name' => 'A員工']);
        $this->otherEmp = User::factory()->create(['department_id' => $this->other->id, 'role' => 'employee', 'name' => 'B員工']);
    }

    private function req(string $code, User $user, array $data, string $status = 'approved', ?string $createdAt = null): FormRequest
    {
        $req = FormRequest::create([
            'form_id' => Form::where('code', $code)->value('id'), 'user_id' => $user->id,
            'request_no' => 'RPT-'.uniqid(), 'title' => '報表測試', 'data' => $data,
            'status' => $status, 'current_step' => 1, 'total_steps' => 1,
        ]);
        if ($createdAt) {
            $req->forceFill(['created_at' => $createdAt])->save();
        }

        return $req;
    }

    private function seedData(): void
    {
        $m = '2026-10';
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '事假', 'start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'days' => 2]);
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '病假', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'days' => 0.5]);
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '事假', 'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'days' => 9]);   // 下個月,不計
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '事假', 'start_date' => '2026-10-08', 'end_date' => '2026-10-08', 'days' => 7], 'pending'); // 未核准,不計
        $this->req('OVERTIME', $this->rdEmp, ['overtime_date' => '2026-10-10', 'hours' => 3.5]);
        $this->req('EXPENSE', $this->rdEmp, ['expense_type' => '出差交通費', 'amount' => 1200]);
        $this->req('EXPENSE', $this->rdEmp, ['expense_type' => '客戶交際費', 'amount' => 800.5]);
        $this->req('PURCHASE', $this->rdEmp, ['estimated_cost' => 30000]);
        $this->req('LEAVE', $this->otherEmp, ['leave_type' => '事假', 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'days' => 1]);
    }

    private function user(string $role): User
    {
        return User::where('email', "$role@eip.local")->firstOrFail();
    }

    public function test_employee_cannot_access_reports(): void
    {
        $this->actingAs($this->user('employee'))->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->user('employee'))->get(route('reports.export'))->assertForbidden();
        $this->get(route('reports.index'))->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        auth()->logout();
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_hr_sees_company_wide_totals(): void
    {
        $this->seedData();

        $this->actingAs($this->user('hr'))->get(route('reports.index', ['month' => '2026-10']))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reports/Index')
                ->where('report.totals.leave_days', 3.5)       // 2 + 0.5 + B 的 1
                ->where('report.totals.leave_count', 3)
                ->where('report.totals.overtime_hours', 3.5)
                ->where('report.totals.expense_amount', 2000.5)
                ->where('report.totals.purchase_amount', 30000)
                ->where('report.leave_by_type.0.type', '事假')
                ->where('report.leave_by_type.0.days', 3)
                ->where('canPickDepartment', true));
    }

    public function test_department_filter_narrows_scope(): void
    {
        $this->seedData();

        $this->actingAs($this->user('admin'))->get(route('reports.index', ['month' => '2026-10', 'department_id' => $this->other->id]))
            ->assertInertia(fn ($p) => $p->where('report.totals.leave_days', 1)->where('report.totals.expense_amount', 0)
                ->has('report.rows', 1)->where('report.rows.0.name', 'B員工'));
    }

    public function test_manager_is_locked_to_own_department(): void
    {
        $this->seedData();
        $manager = User::factory()->create(['department_id' => $this->rd->id, 'role' => 'manager']);

        // 試圖用 department_id 看別的部門 → 被忽略
        $this->actingAs($manager)->get(route('reports.index', ['month' => '2026-10', 'department_id' => $this->other->id]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('report.totals.leave_days', 2.5)->where('canPickDepartment', false)
                ->where('filters.department_id', $this->rd->id)->has('departments', 1));
    }

    public function test_manager_without_department_sees_nothing(): void
    {
        $this->seedData();
        $manager = User::factory()->create(['department_id' => null, 'role' => 'manager']);

        $this->actingAs($manager)->get(route('reports.index', ['month' => '2026-10']))
            ->assertInertia(fn ($p) => $p->where('report.totals.leave_days', 0)->has('report.rows', 0));
    }

    public function test_status_counts_cover_all_statuses_in_month(): void
    {
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '事假', 'days' => 1], 'pending', '2026-10-03 10:00:00');
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '事假', 'days' => 1], 'rejected', '2026-10-04 10:00:00');
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '事假', 'days' => 1], 'approved', '2026-09-30 10:00:00');

        $this->actingAs($this->user('hr'))->get(route('reports.index', ['month' => '2026-10', 'department_id' => $this->rd->id]))
            ->assertInertia(fn ($p) => $p->where('report.status_counts.pending', 1)->where('report.status_counts.rejected', 1)->where('report.status_counts.approved', 0));
    }

    public function test_malformed_inputs_fall_back_safely_instead_of_500(): void
    {
        $hr = $this->user('hr');
        $current = Carbon::now()->format('Y-m');

        foreach (['abc', '2026-13', "2026-10'; --", '2026-1', ['x']] as $bad) {
            $this->actingAs($hr)->get(route('reports.index', ['month' => $bad]))->assertOk()
                ->assertInertia(fn ($p) => $p->where('filters.month', $current));
            $this->actingAs($hr)->get(route('reports.export', ['month' => $bad]))->assertOk();
        }
        $this->actingAs($hr)->get(route('reports.index', ['department_id' => 'abc']))->assertOk();
        $this->actingAs($hr)->get(route('reports.index', ['department_id' => 99999]))->assertOk();
    }

    public function test_export_csv_contents_and_audit(): void
    {
        $this->seedData();
        $this->rdEmp->update(['name' => '=cmd|calc']);

        $response = $this->actingAs($this->user('hr'))->get(route('reports.export', ['month' => '2026-10', 'department_id' => $this->rd->id]));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('請假天數', $csv);
        $this->assertStringContainsString("'=cmd|calc", $csv, 'Formula Injection 需跳脫');
        $this->assertStringContainsString('2.5,3.5,2000.5,30000', $csv);
        $this->assertStringContainsString('合計', $csv);
        $this->assertStringNotContainsString('B員工', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export_form_report']);
    }

    public function test_lookback_includes_leave_applied_in_advance_but_not_ancient_requests(): void
    {
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '特休假', 'start_date' => '2026-10-15', 'days' => 3], 'approved', '2026-08-01 09:00:00');
        $this->req('LEAVE', $this->rdEmp, ['leave_type' => '特休假', 'start_date' => '2026-10-16', 'days' => 4], 'approved', '2024-01-01 09:00:00');

        $this->actingAs($this->user('hr'))->get(route('reports.index', ['month' => '2026-10', 'department_id' => $this->rd->id]))
            ->assertInertia(fn ($p) => $p->where('report.totals.leave_days', 3));
    }
}
