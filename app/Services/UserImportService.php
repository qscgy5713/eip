<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * 員工名冊 CSV 解析與逐列驗證。不寫入資料庫,只回傳每列的結果,由控制器決定是否匯入。
 */
class UserImportService
{
    public const MAX_ROWS = 500;

    /** 欄位標題(中文或英文皆可)→ 內部欄位名 */
    private const HEADERS = [
        '姓名' => 'name', 'name' => 'name',
        'email' => 'email', '電子郵件' => 'email', '信箱' => 'email',
        '員工編號' => 'employee_no', 'employee_no' => 'employee_no',
        '部門代碼' => 'department_code', 'department_code' => 'department_code',
        '職稱' => 'job_title', 'job_title' => 'job_title',
        '角色' => 'role', 'role' => 'role',
        '電話' => 'phone', 'phone' => 'phone',
        '初始密碼' => 'password', 'password' => 'password',
    ];

    private const ROLES = [
        'admin' => 'admin', '系統管理員' => 'admin', '管理員' => 'admin',
        'manager' => 'manager', '部門主管' => 'manager', '主管' => 'manager',
        'hr' => 'hr', '人資' => 'hr', '人資管理員' => 'hr',
        'employee' => 'employee', '一般員工' => 'employee', '員工' => 'employee', '' => 'employee',
    ];

    public static function templateCsv(): string
    {
        $rows = [
            ['姓名', 'Email', '員工編號', '部門代碼', '職稱', '角色', '電話', '初始密碼'],
            ['王小明', 'xiaoming.wang@example.com', 'E1001', 'RD', '軟體工程師', '一般員工', '0912-345-678', ''],
            ['李大華', 'dahua.li@example.com', 'E1002', 'HR', '人資專員', '人資', '', 'Welcome2026'],
        ];

        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row))."\r\n";
        }

        return $out;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, fatal: ?string}
     *   每列:line(檔案中的行號)、data(清理後欄位)、errors(錯誤字串陣列)、department_id、role
     */
    public function parse(string $raw, User $operator): array
    {
        $raw = $this->toUtf8($raw);
        $lines = $this->readCsv($raw);

        if (count($lines) < 2) {
            return ['rows' => [], 'fatal' => '檔案沒有資料列,請依範本填寫(第一列為標題)。'];
        }

        $header = array_map(fn ($h) => self::HEADERS[strtolower(trim((string) $h))] ?? self::HEADERS[trim((string) $h)] ?? null, $lines[0]);
        foreach (['name', 'email'] as $required) {
            if (! in_array($required, $header, true)) {
                return ['rows' => [], 'fatal' => '找不到必要欄位「'.($required === 'name' ? '姓名' : 'Email').'」,請使用範本的標題列。'];
            }
        }

        $body = array_slice($lines, 1);
        if (count($body) > self::MAX_ROWS) {
            return ['rows' => [], 'fatal' => '單次最多匯入 '.self::MAX_ROWS.' 筆,請分批上傳。'];
        }

        $departments = Department::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtolower((string) $code) => $id]);
        $seenEmail = [];
        $seenNo = [];
        $rows = [];

        foreach ($body as $i => $cells) {
            $data = array_fill_keys(array_values(array_unique(array_filter(self::HEADERS))), '');
            foreach ($header as $col => $key) {
                if ($key !== null) {
                    $data[$key] = trim((string) ($cells[$col] ?? ''));
                }
            }
            $data['email'] = strtolower($data['email']);
            $errors = [];

            $roleKey = self::ROLES[strtolower($data['role'])] ?? self::ROLES[$data['role']] ?? null;
            if ($roleKey === null) {
                $errors[] = "角色「{$data['role']}」無法辨識(可填:一般員工、部門主管、人資、系統管理員)";
            } elseif ($roleKey === 'admin' && ! $operator->isAdmin()) {
                $errors[] = '僅系統管理員可匯入管理員角色';
            }

            $departmentId = null;
            if ($data['department_code'] !== '') {
                $departmentId = $departments[strtolower($data['department_code'])] ?? null;
                if (! $departmentId) {
                    $errors[] = "找不到部門代碼「{$data['department_code']}」";
                }
            }

            $v = Validator::make($data, [
                'name' => 'required|string|max:50',
                'email' => 'required|string|email|max:100|unique:users,email',
                'employee_no' => 'nullable|string|max:30|unique:users,employee_no',
                'job_title' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:30',
                'password' => ['nullable', 'string', Password::defaults()],
            ], [], [
                'name' => '姓名', 'email' => 'Email', 'employee_no' => '員工編號',
                'job_title' => '職稱', 'phone' => '電話', 'password' => '初始密碼',
            ]);
            $errors = array_merge($errors, $v->errors()->all());

            if ($data['email'] !== '') {
                if (isset($seenEmail[$data['email']])) {
                    $errors[] = "Email 與第 {$seenEmail[$data['email']]} 行重複";
                }
                $seenEmail[$data['email']] ??= $i + 2;
            }
            if ($data['employee_no'] !== '') {
                if (isset($seenNo[$data['employee_no']])) {
                    $errors[] = "員工編號與第 {$seenNo[$data['employee_no']]} 行重複";
                }
                $seenNo[$data['employee_no']] ??= $i + 2;
            }

            $rows[] = [
                'line' => $i + 2,
                'data' => $data,
                'errors' => array_values(array_unique($errors)),
                'department_id' => $departmentId,
                'role' => $roleKey,
            ];
        }

        return ['rows' => $rows, 'fatal' => null];
    }

    /** Excel 在繁中 Windows 另存的「CSV」多為 Big5,非 UTF-8 時自動轉碼。 */
    private function toUtf8(string $raw): string
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'BIG5');
        }

        return $raw;
    }

    /** @return array<int, array<int, string|null>> 略過全空白的列 */
    private function readCsv(string $raw): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $lines = [];
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $lines[] = $cells;
        }
        fclose($handle);

        return $lines;
    }
}
