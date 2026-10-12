<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EipSystemNotification;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_only_view_their_own_published_payslips()
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $otherEmployee = User::factory()->create(['role' => 'employee']);

        // 自己的已發布薪資單
        $myPublished = Payslip::create([
            'user_id' => $employee->id,
            'period' => '2026-09',
            'base_salary' => 60000,
            'gross_pay' => 60000,
            'net_pay' => 55000,
            'status' => 'published',
        ]);

        // 自己的草稿薪資單
        $myDraft = Payslip::create([
            'user_id' => $employee->id,
            'period' => '2026-10',
            'base_salary' => 60000,
            'gross_pay' => 60000,
            'net_pay' => 55000,
            'status' => 'draft',
        ]);

        // 他人的已發布薪資單
        $otherPublished = Payslip::create([
            'user_id' => $otherEmployee->id,
            'period' => '2026-09',
            'base_salary' => 70000,
            'gross_pay' => 70000,
            'net_pay' => 64000,
            'status' => 'published',
        ]);

        $response = $this->actingAs($employee)->get(route('payslips.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Payslips/Index')
            ->has('payslips.data', 1)
            ->where('payslips.data.0.id', $myPublished->id)
        );
    }

    public function test_employee_cannot_view_other_employees_payslip_idor_protection()
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $otherEmployee = User::factory()->create(['role' => 'employee']);

        $otherPayslip = Payslip::create([
            'user_id' => $otherEmployee->id,
            'period' => '2026-10',
            'base_salary' => 80000,
            'gross_pay' => 80000,
            'net_pay' => 73000,
            'status' => 'published',
        ]);

        $response = $this->actingAs($employee)->get(route('payslips.show', $otherPayslip->id));

        $response->assertStatus(403);
    }

    public function test_employee_cannot_view_own_draft_payslip()
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $draftPayslip = Payslip::create([
            'user_id' => $employee->id,
            'period' => '2026-10',
            'base_salary' => 80000,
            'gross_pay' => 80000,
            'net_pay' => 73000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($employee)->get(route('payslips.show', $draftPayslip->id));

        $response->assertStatus(403);
    }

    public function test_viewing_payslip_creates_audit_log()
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $payslip = Payslip::create([
            'user_id' => $employee->id,
            'period' => '2026-09',
            'base_salary' => 60000,
            'gross_pay' => 60000,
            'net_pay' => 55000,
            'status' => 'published',
        ]);

        $response = $this->actingAs($employee)->get(route('payslips.show', $payslip->id));

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $employee->id,
            'action' => 'payslip_view',
        ]);
    }

    public function test_hr_can_create_payslip_and_totals_are_calculated()
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $employee = User::factory()->create(['role' => 'employee']);

        $payload = [
            'user_id' => $employee->id,
            'period' => '2026-10',
            'base_salary' => 50000,
            'allowance' => 5000,
            'bonus' => 10000,
            'overtime_pay' => 2000,
            'other_additions' => 1000,
            'labor_insurance' => 1200,
            'health_insurance' => 900,
            'pension_self' => 3000,
            'tax_withholding' => 1500,
            'other_deductions' => 400,
            'note' => '10月正式計薪',
            'status' => 'draft',
        ];

        $response = $this->actingAs($hr)->post(route('payslips.store'), $payload);

        $response->assertRedirect();

        // 應發 = 50000 + 5000 + 10000 + 2000 + 1000 = 68000
        // 應扣 = 1200 + 900 + 3000 + 1500 + 400 = 7000
        // 實發 = 68000 - 7000 = 61000
        $this->assertDatabaseHas('payslips', [
            'user_id' => $employee->id,
            'year' => 2026,
            'month' => 10,
            'total_earnings' => 68000,
            'total_deductions' => 7000,
            'net_pay' => 61000,
            'status' => 'draft',
        ]);
    }

    public function test_regular_employee_cannot_create_or_update_payslip()
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($employee)->post(route('payslips.store'), [
            'user_id' => $employee->id,
            'period' => '2026-10',
            'base_salary' => 50000,
        ]);

        $response->assertStatus(403);
    }

    public function test_hr_can_batch_publish_payslips_and_send_notifications()
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $emp1 = User::factory()->create(['role' => 'employee']);
        $emp2 = User::factory()->create(['role' => 'employee']);

        $p1 = Payslip::create([
            'user_id' => $emp1->id,
            'period' => '2026-10',
            'base_salary' => 50000,
            'status' => 'draft',
        ]);

        $p2 = Payslip::create([
            'user_id' => $emp2->id,
            'period' => '2026-10',
            'base_salary' => 55000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($hr)->post(route('payslips.publishBatch'), [
            'period' => '2026-10',
        ]);

        $response->assertRedirect();

        $this->assertEquals('published', $p1->fresh()->status);
        $this->assertEquals('published', $p2->fresh()->status);
        $this->assertNotNull($p1->fresh()->published_at);

        // 驗證同仁收到站內系統通知
        $this->assertEquals(1, $emp1->notifications()->count());
        $this->assertEquals(1, $emp2->notifications()->count());
    }

    public function test_dashboard_loads_latest_published_payslip()
    {
        $employee = User::factory()->create(['role' => 'employee']);

        Payslip::create([
            'user_id' => $employee->id,
            'period' => '2026-09',
            'base_salary' => 50000,
            'gross_pay' => 50000,
            'net_pay' => 46000,
            'status' => 'published',
        ]);

        $latest = Payslip::create([
            'user_id' => $employee->id,
            'period' => '2026-10',
            'base_salary' => 52000,
            'gross_pay' => 52000,
            'net_pay' => 48000,
            'status' => 'published',
        ]);

        $response = $this->actingAs($employee)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('latestPayslip.id', $latest->id)
            ->where('latestPayslip.net_pay', '48000.00')
        );
    }
}
