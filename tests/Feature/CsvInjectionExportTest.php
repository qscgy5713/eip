<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CsvExportService;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvInjectionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitizer_neutralises_formula_prefixes_and_keeps_normal_values(): void
    {
        foreach (['=1+1', '+cmd|calc', '-2+3', '@SUM(A1)', "\t=1", '=HYPERLINK("http://evil","x")'] as $v) {
            $this->assertSame("'" . $v, CsvExportService::sanitizeCell($v), $v);
        }
        foreach (['王小明', 'A-100', '2026-10-13', '', null, 42] as $v) {
            $this->assertSame($v, CsvExportService::sanitizeCell($v));
        }
    }

    public function test_exports_never_contain_a_raw_formula_cell_from_a_malicious_name(): void
    {
        $this->seed(EipDatabaseSeeder::class);
        $evil = '=HYPERLINK("http://evil.example","click")';
        $emp = User::where('email', 'employee@eip.local')->first();
        $emp->update(['name' => $evil]);
        $admin = User::where('email', 'admin@eip.local')->first();

        $urls = ['/org-management/export-roster', '/attendance/reports/export-summary', '/attendance/reports/export-details', '/reports/export?month=' . now()->format('Y-m'), '/vehicles/export', '/visitor-reservations/export', '/trainings/export'];
        $checked = 0;
        foreach ($urls as $url) {
            $res = $this->actingAs($admin)->get($url);
            $this->assertSame(200, $res->getStatusCode(), "{$url} 應可匯出");
            $body = $res->streamedContent();
            $checked++;
            // 欄位開頭(行首或逗號後,可能被引號包住)不得直接是 =
            $this->assertDoesNotMatchRegularExpression('/(^|,|\n)"?=HYPERLINK/', $body, "{$url} 含未消毒的公式");
        }
        $this->assertSame(count($urls), $checked);
    }
}
