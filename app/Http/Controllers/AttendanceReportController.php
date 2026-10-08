<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    /**
     * 檢查是否有權限查閱考勤月報 (HR, Admin, Manager)
     */
    protected function authorizeReport(User $user): void
    {
        if (!in_array($user->role, ['hr', 'admin', 'manager'])) {
            abort(403, '僅人資、部門主管與系統管理員具備調閱考勤月報權限。');
        }
    }

    /**
     * 考勤月報統計看板
     */
    public function index(Request $request): Response
    {
        $currentUser = $request->user();
        $this->authorizeReport($currentUser);

        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $departmentId = $request->input('department_id');
        $search = $request->input('search');

        // 主管預設僅能看自己部門
        if ($currentUser->role === 'manager' && empty($departmentId)) {
            $departmentId = $currentUser->department_id;
        }

        $startDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $endDate = Carbon::parse($month)->endOfMonth()->toDateString();

        // 查詢符合條件的員工
        $usersQuery = User::with('department')
            ->where('status', 'active');

        if ($departmentId) {
            $usersQuery->where('department_id', $departmentId);
        }

        if ($search) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_no', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->orderBy('department_id')->orderBy('name')->get();
        $userIds = $users->pluck('id');

        // 撈取該月份的所有打卡紀錄
        $attendances = Attendance::whereIn('user_id', $userIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get()
            ->groupBy('user_id');

        // 統計每位員工的出勤數據
        $userReports = $users->map(function ($u) use ($attendances) {
            $records = $attendances->get($u->id, collect());

            $presentDays = $records->whereNotNull('clock_in_at')->count();
            $totalHours = round($records->sum('work_hours'), 2);
            $lateCount = $records->where('status', 'late')->count();
            $earlyLeaveCount = $records->where('status', 'early_leave')->count();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'employee_no' => $u->employee_no,
                'department' => $u->department?->name ?? '無部門',
                'job_title' => $u->job_title ?? '同仁',
                'present_days' => $presentDays,
                'total_hours' => $totalHours,
                'late_count' => $lateCount,
                'early_leave_count' => $earlyLeaveCount,
                'daily_records' => $records->map(fn ($r) => [
                    'id' => $r->id,
                    'date' => Carbon::parse($r->date)->format('Y-m-d'),
                    'clock_in' => $r->clock_in_at ? Carbon::parse($r->clock_in_at)->format('H:i:s') : '-',
                    'clock_out' => $r->clock_out_at ? Carbon::parse($r->clock_out_at)->format('H:i:s') : '-',
                    'work_hours' => $r->work_hours ?? 0,
                    'status' => $r->status,
                    'note' => $r->note,
                ])->values(),
            ];
        });

        // 全局 KPI 指標
        $summaryStats = [
            'total_users' => $users->count(),
            'total_work_hours' => round($userReports->sum('total_hours'), 1),
            'total_late_count' => $userReports->sum('late_count'),
            'total_early_leave_count' => $userReports->sum('early_leave_count'),
        ];

        $departments = Department::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Attendance/Report', [
            'userReports' => $userReports,
            'summaryStats' => $summaryStats,
            'departments' => $departments,
            'filters' => [
                'month' => $month,
                'department_id' => $departmentId,
                'search' => $search,
            ],
            'canExport' => in_array($currentUser->role, ['hr', 'admin']),
        ]);
    }

    /**
     * 匯出月度考勤彙總 CSV (含 UTF-8 BOM，防止 Excel 亂碼)
     */
    public function exportSummary(Request $request): StreamedResponse
    {
        $currentUser = $request->user();
        $this->authorizeReport($currentUser);

        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $departmentId = $request->input('department_id');

        $startDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $endDate = Carbon::parse($month)->endOfMonth()->toDateString();

        $usersQuery = User::with('department')->where('status', 'active');
        if ($departmentId) {
            $usersQuery->where('department_id', $departmentId);
        }
        $users = $usersQuery->orderBy('department_id')->orderBy('name')->get();

        $attendances = Attendance::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('user_id');

        AuditLog::log(
            action: 'export_attendance_summary',
            description: "匯出了 {$month} 月份考勤月報彙總表 (共 {$users->count()} 筆員工紀錄)",
            details: ['month' => $month, 'department_id' => $departmentId]
        );

        $filename = "attendance_summary_{$month}.csv";

        return response()->streamDownload(function () use ($users, $attendances) {
            $handle = fopen('php://output', 'w');
            // 寫入 UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV 標頭
            fputcsv($handle, ['員工工號', '員工姓名', '部門', '職稱', '出勤天數', '累計工時(小時)', '遲到次數', '早退次數']);

            foreach ($users as $user) {
                $records = $attendances->get($user->id, collect());
                $presentDays = $records->whereNotNull('clock_in_at')->count();
                $totalHours = round($records->sum('work_hours'), 2);
                $lateCount = $records->where('status', 'late')->count();
                $earlyLeaveCount = $records->where('status', 'early_leave')->count();

                fputcsv($handle, [
                    $user->employee_no ?? '-',
                    $user->name,
                    $user->department?->name ?? '無部門',
                    $user->job_title ?? '-',
                    $presentDays,
                    $totalHours,
                    $lateCount,
                    $earlyLeaveCount,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 匯出月度考勤每日明細 CSV
     */
    public function exportDetails(Request $request): StreamedResponse
    {
        $currentUser = $request->user();
        $this->authorizeReport($currentUser);

        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $departmentId = $request->input('department_id');

        $startDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $endDate = Carbon::parse($month)->endOfMonth()->toDateString();

        $query = Attendance::with(['user.department'])
            ->whereBetween('date', [$startDate, $endDate]);

        if ($departmentId) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId));
        }

        $attendances = $query->orderBy('date')->orderBy('user_id')->get();

        AuditLog::log(
            action: 'export_attendance_details',
            description: "匯出了 {$month} 月份每日打卡考勤明細 (共 {$attendances->count()} 筆紀錄)",
            details: ['month' => $month, 'department_id' => $departmentId]
        );

        $filename = "attendance_details_{$month}.csv";

        return response()->streamDownload(function () use ($attendances) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['日期', '員工工號', '姓名', '部門', '上班打卡時間', '下班打卡時間', '當日工時', '狀態', '打卡備註']);

            $statusMap = [
                'normal' => '正常',
                'late' => '遲到',
                'early_leave' => '早退',
                'absent' => '缺勤',
                'holiday' => '休假/節假日',
            ];

            foreach ($attendances as $row) {
                fputcsv($handle, [
                    Carbon::parse($row->date)->format('Y-m-d'),
                    $row->user?->employee_no ?? '-',
                    $row->user?->name ?? '未知',
                    $row->user?->department?->name ?? '-',
                    $row->clock_in_at ? Carbon::parse($row->clock_in_at)->format('H:i:s') : '-',
                    $row->clock_out_at ? Carbon::parse($row->clock_out_at)->format('H:i:s') : '-',
                    $row->work_hours ?? 0,
                    $statusMap[$row->status] ?? $row->status,
                    $row->note ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
