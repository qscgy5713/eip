<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 全公司資料的 CSV 匯出僅限有權限的角色,不可由一般員工直接打網址下載。 */
class ExportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    private function as(string $role): self
    {
        return $this->actingAs(User::where('email', "$role@eip.local")->firstOrFail());
    }

    public function test_employee_cannot_export_company_wide_logs(): void
    {
        foreach (['/visitor-reservations/export', '/vehicles/export', '/trainings/export'] as $url) {
            $this->as('employee')->get($url)->assertForbidden();
        }
    }

    public function test_visitor_and_vehicle_exports_allowed_for_admin_and_manager(): void
    {
        foreach (['admin', 'manager'] as $role) {
            $this->as($role)->get('/visitor-reservations/export')->assertOk();
            $this->as($role)->get('/vehicles/export')->assertOk();
        }
    }

    public function test_training_export_allowed_for_admin_and_hr_only(): void
    {
        $this->as('admin')->get('/trainings/export')->assertOk();
        $this->as('hr')->get('/trainings/export')->assertOk();
        $this->as('manager')->get('/trainings/export')->assertForbidden();
    }
}
