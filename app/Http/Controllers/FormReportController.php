<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Services\FormReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** 表單統計報表中心:請假 / 加班 / 報銷 / 請購月報(人資、系統管理員全公司;部門主管僅限本部門) */
class FormReportController extends Controller
{
    public function __construct(private FormReportService $reports) {}

    private function authorizeReport(User $user): void
    {
        abort_unless(in_array($user->role, ['admin', 'hr', 'manager'], true), 403, '僅人資、部門主管與系統管理員可查閱表單統計報表。');
    }

    /** @return array{0: string, 1: ?int, 2: ?array<int>} [月份, 部門ID, 部門ID清單(null=全公司)] */
    private function resolveScope(Request $request): array
    {
        $user = $request->user();
        $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $month = $request->input('month') ?: Carbon::now()->format('Y-m');
        $departmentId = $request->integer('department_id') ?: null;

        // 主管強制只能看自己所屬部門(防水平越權),沒有部門的主管看不到任何人
        if ($user->role === 'manager') {
            $departmentId = $user->department_id;

            return [$month, $departmentId, $departmentId ? [$departmentId] : [0]];
        }

        return [$month, $departmentId, $departmentId ? [$departmentId] : null];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeReport($user);
        [$month, $departmentId, $ids] = $this->resolveScope($request);

        return Inertia::render('Reports/Index', [
            'report' => $this->reports->build($month, $ids),
            'filters' => ['month' => $month, 'department_id' => $departmentId],
            'departments' => $user->role === 'manager'
                ? Department::where('id', $user->department_id)->get(['id', 'name'])
                : Department::orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'canPickDepartment' => $user->role !== 'manager',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $this->authorizeReport($user);
        [$month, $departmentId, $ids] = $this->resolveScope($request);
        $report = $this->reports->build($month, $ids);

        AuditLog::log(
            action: 'export_form_report',
            description: "{$user->name} 匯出了 {$month} 表單統計月報 CSV",
            details: ['month' => $month, 'department_id' => $departmentId, 'rows' => count($report['rows'])]
        );

        $safe = function (mixed $val): string {
            $val = (string) ($val ?? '');
            if ($val !== '' && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$val;
            }

            return $val;
        };

        return response()->streamDownload(function () use ($report, $month, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ["表單統計月報 {$month}(已核准單據)"], ',', '"', '');
            fputcsv($out, ['姓名', '員工編號', '部門', '請假天數', '加班時數', '報銷金額', '請購預算'], ',', '"', '');
            foreach ($report['rows'] as $r) {
                fputcsv($out, [
                    $safe($r['name']), $safe($r['employee_no']), $safe($r['department']),
                    $r['leave_days'], $r['overtime_hours'], $r['expense_amount'], $r['purchase_amount'],
                ], ',', '"', '');
            }
            $t = $report['totals'];
            fputcsv($out, ['合計', '', '', $t['leave_days'], $t['overtime_hours'], $t['expense_amount'], $t['purchase_amount']], ',', '"', '');
            fclose($out);
        }, "form_report_{$month}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
