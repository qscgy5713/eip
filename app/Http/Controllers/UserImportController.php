<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Services\UserImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** 員工名冊 CSV 批次匯入(管理員 / 人資) */
class UserImportController extends Controller
{
    public function __construct(
        private UserImportService $importer,
        private LeaveBalanceService $leaveBalanceService,
    ) {}

    private function authorizeOperator(Request $request): User
    {
        $operator = $request->user();
        abort_unless($operator->isAdmin() || $operator->isHr(), 403, '僅限系統管理員或人資管理員可批次匯入同仁。');

        return $operator;
    }

    public function show(Request $request): Response
    {
        $this->authorizeOperator($request);

        return Inertia::render('OrgManagement/Import', [
            'maxRows' => UserImportService::MAX_ROWS,
            'result' => session('import_result'),
        ]);
    }

    public function template(Request $request)
    {
        $this->authorizeOperator($request);

        return response(UserImportService::templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="employee_import_template.csv"',
        ]);
    }

    /** mode=preview:只檢查不寫入;mode=commit:全部無誤才整批寫入 */
    public function store(Request $request): RedirectResponse
    {
        $operator = $this->authorizeOperator($request);

        $request->validate([
            'file' => ['required', 'file', 'max:1024', 'mimes:csv,txt'],
            'mode' => ['required', 'in:preview,commit'],
        ], [
            'file.required' => '請選擇要上傳的 CSV 檔案。',
            'file.max' => '檔案不可超過 1 MB。',
            'file.mimes' => '請上傳 .csv 檔案。',
        ]);

        $parsed = $this->importer->parse((string) file_get_contents($request->file('file')->getRealPath()), $operator);

        if ($parsed['fatal']) {
            return back()->withErrors(['file' => $parsed['fatal']]);
        }

        $rows = $parsed['rows'];
        $errorCount = count(array_filter($rows, fn ($r) => $r['errors']));
        $summary = [
            'total' => count($rows),
            'valid' => count($rows) - $errorCount,
            'invalid' => $errorCount,
            'rows' => array_map(fn ($r) => [
                'line' => $r['line'],
                'name' => $r['data']['name'],
                'email' => $r['data']['email'],
                'employee_no' => $r['data']['employee_no'],
                'department_code' => $r['data']['department_code'],
                'job_title' => $r['data']['job_title'],
                'role' => $r['role'],
                'errors' => $r['errors'],
            ], $rows),
        ];

        if ($request->input('mode') === 'preview' || $errorCount > 0) {
            return back()->with('import_result', $summary + ['mode' => 'preview', 'committed' => false]);
        }

        $generated = [];
        $year = (int) date('Y');
        DB::transaction(function () use ($rows, $year, &$generated) {
            foreach ($rows as $r) {
                $d = $r['data'];
                $plain = $d['password'] !== '' ? null : Str::random(10).'a'.random_int(0, 9); // 保證含英文字母與數字
                $user = User::create([
                    'name' => $d['name'],
                    'email' => $d['email'],
                    'employee_no' => $d['employee_no'] ?: null,
                    'department_id' => $r['department_id'],
                    'job_title' => $d['job_title'] ?: null,
                    'role' => $r['role'],
                    'phone' => $d['phone'] ?: null,
                    'password' => Hash::make($plain ?? $d['password']),
                    'must_change_password' => true,
                    'status' => 'active',
                ]);
                $this->leaveBalanceService->initUserBalances($user, $year);
                if ($plain) {
                    $generated[] = ['name' => $user->name, 'email' => $user->email, 'password' => $plain];
                }
            }
        });

        AuditLog::log(
            action: 'import_users_csv',
            description: "管理者 {$operator->name} 以 CSV 批次匯入了 {$summary['total']} 位同仁帳號",
            details: ['total' => $summary['total'], 'auto_password_count' => count($generated)]
        );

        return back()->with('import_result', $summary + ['mode' => 'commit', 'committed' => true, 'generated' => $generated]);
    }
}
