<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $month = $request->query('month', now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month)->startOfMonth()->toDateString();
        $endOfMonth = Carbon::parse($month)->endOfMonth()->toDateString();

        // 個人本月打卡清單
        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderByDesc('date')
            ->get();

        // 今日打卡狀態
        $today = now()->toDateString();
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        // 團隊今日出勤概況 (主管與管理員可見)
        $teamAttendances = [];
        if ($user->isManager()) {
            $teamQuery = User::with(['department', 'attendances' => fn($q) => $q->whereDate('date', $today)])
                ->where('status', 'active');

            if (!$user->isAdmin()) {
                $teamQuery->where('department_id', $user->department_id);
            }

            $teamAttendances = $teamQuery->get()->map(function ($member) {
                $att = $member->attendances->first();
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'department' => $member->department?->name ?? '公司同仁',
                    'clock_in_at' => $att?->clock_in_at?->format('H:i') ?? null,
                    'clock_out_at' => $att?->clock_out_at?->format('H:i') ?? null,
                    'status' => $att ? $att->status : 'absent',
                    'work_hours' => $att ? $att->work_hours : 0,
                ];
            });
        }

        return Inertia::render('Attendance/Index', [
            'attendances' => $attendances,
            'todayAttendance' => $todayAttendance,
            'month' => $month,
            'stats' => [
                'totalWorkHours' => $attendances->sum('work_hours'),
                'daysWorked' => $attendances->whereNotNull('clock_in_at')->count(),
                'lateCount' => $attendances->where('status', 'late')->count(),
                'earlyLeaveCount' => $attendances->where('status', 'early_leave')->count(),
            ],
            'teamAttendances' => $teamAttendances,
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'date' => $today],
            [
                'clock_in_at' => now(),
                'clock_in_ip' => $request->ip(),
                'clock_in_location' => $request->input('location', '辦公室網段'),
                'status' => now()->format('H:i') > '09:30' ? 'late' : 'normal',
            ]
        );

        AuditLog::log(
            action: 'clock_in',
            description: "同仁 {$user->name} 完成了上班打卡（狀態：" . ($attendance->status === 'late' ? '遲到' : '正常') . "）",
            auditable: $attendance,
            details: ['time' => now()->toTimeString(), 'status' => $attendance->status]
        );

        return redirect()->back()->with('success', '上班打卡成功！');
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', $user->id)->whereDate('date', $today)->first();

        if (!$attendance) {
            return redirect()->back()->with('error', '今日尚無上班打卡紀錄，請先填寫忘刷/補打卡申請單或先執行上班打卡。');
        }

        $attendance->clock_out_at = now();
        $attendance->clock_out_ip = $request->ip();
        $attendance->clock_out_location = $request->input('location', '辦公室網段');
        $attendance->calculateWorkHours();
        $attendance->save();

        AuditLog::log(
            action: 'clock_out',
            description: "同仁 {$user->name} 完成了下班打卡（本日工時 {$attendance->work_hours} 小時）",
            auditable: $attendance,
            details: ['time' => now()->toTimeString(), 'work_hours' => $attendance->work_hours]
        );

        return redirect()->back()->with('success', '下班打卡成功！今日工時已結算。');
    }
}
