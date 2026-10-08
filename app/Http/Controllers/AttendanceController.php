<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\GeofenceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        protected GeofenceService $geofenceService
    ) {}

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
                    'clock_in_type' => $att?->clock_in_type ?? 'office',
                    'clock_in_distance' => $att?->clock_in_distance,
                    'status' => $att ? $att->status : 'absent',
                    'work_hours' => $att ? $att->work_hours : 0,
                    'field_work_note' => $att?->field_work_note,
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
            'geofenceConfig' => $this->geofenceService->getOfficeConfig(),
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $existing = Attendance::where('user_id', $user->id)->whereDate('date', $today)->first();
        if ($existing && $existing->clock_in_at) {
            return redirect()->back()->with('error', '您今日已完成上班打卡。');
        }

        $lat = $request->input('latitude') !== null ? (float) $request->input('latitude') : null;
        $lng = $request->input('longitude') !== null ? (float) $request->input('longitude') : null;
        $fieldNote = $request->input('field_work_note');

        $eval = $this->geofenceService->evaluateLocation($lat, $lng);
        $locationDesc = ($eval['type'] === 'unverified' && $request->filled('location'))
            ? $request->input('location')
            : $eval['location_desc'];

        if (!$existing) {
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'date' => $today,
                'clock_in_at' => now(),
                'clock_in_ip' => $request->ip(),
                'clock_in_location' => $locationDesc,
                'clock_in_lat' => $lat,
                'clock_in_lng' => $lng,
                'clock_in_distance' => $eval['distance'],
                'clock_in_type' => $eval['type'],
                'field_work_note' => $fieldNote,
                'status' => now()->format('H:i') > '09:30' ? 'late' : 'normal',
            ]);
        } else {
            $existing->update([
                'clock_in_at' => now(),
                'clock_in_ip' => $request->ip(),
                'clock_in_location' => $locationDesc,
                'clock_in_lat' => $lat,
                'clock_in_lng' => $lng,
                'clock_in_distance' => $eval['distance'],
                'clock_in_type' => $eval['type'],
                'field_work_note' => $fieldNote,
                'status' => now()->format('H:i') > '09:30' ? 'late' : 'normal',
            ]);
            $attendance = $existing;
        }

        $typeDesc = match($eval['type']) {
            'office' => "【辦公室內勤打卡】距總部 {$eval['distance']}m",
            'remote' => "【外勤/遠端打卡】距總部 {$eval['distance']}m" . (!empty($fieldNote) ? "（事由：{$fieldNote}）" : ''),
            default => '【IP網段打卡】未提供GPS定位',
        };

        AuditLog::log(
            action: 'clock_in',
            description: "同仁 {$user->name} 完成了上班打卡（{$typeDesc}，狀態：" . ($attendance->status === 'late' ? '遲到' : '正常') . "）",
            auditable: $attendance,
            details: [
                'time' => now()->toTimeString(),
                'status' => $attendance->status,
                'type' => $eval['type'],
                'distance' => $eval['distance'],
                'field_work_note' => $fieldNote,
            ]
        );

        $successMsg = $eval['type'] === 'office'
            ? "上班打卡成功！（辦公室圍欄內，距離 {$eval['distance']}m）"
            : ($eval['type'] === 'remote' ? "上班打卡成功！（已記錄為外勤/遠端打卡，距離總部 " . $this->geofenceService->formatDistance($eval['distance'] ?? 0) . "）" : "上班打卡成功！");

        return redirect()->back()->with('success', $successMsg);
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', $user->id)->whereDate('date', $today)->first();

        if (!$attendance) {
            return redirect()->back()->with('error', '今日尚無上班打卡紀錄，請先填寫忘刷/補打卡申請單或先執行上班打卡。');
        }

        $lat = $request->input('latitude') !== null ? (float) $request->input('latitude') : null;
        $lng = $request->input('longitude') !== null ? (float) $request->input('longitude') : null;
        $fieldNote = $request->input('field_work_note');

        $eval = $this->geofenceService->evaluateLocation($lat, $lng);
        $locationDesc = ($eval['type'] === 'unverified' && $request->filled('location'))
            ? $request->input('location')
            : $eval['location_desc'];

        $attendance->clock_out_at = now();
        $attendance->clock_out_ip = $request->ip();
        $attendance->clock_out_location = $locationDesc;
        $attendance->clock_out_lat = $lat;
        $attendance->clock_out_lng = $lng;
        $attendance->clock_out_distance = $eval['distance'];
        $attendance->clock_out_type = $eval['type'];

        if (!empty($fieldNote)) {
            $attendance->field_work_note = trim(($attendance->field_work_note ? $attendance->field_work_note . "；" : "") . $fieldNote);
        }

        $attendance->calculateWorkHours();
        $attendance->save();

        AuditLog::log(
            action: 'clock_out',
            description: "同仁 {$user->name} 完成了下班打卡（本日工時 {$attendance->work_hours} 小時，地點：{$eval['location_desc']}）",
            auditable: $attendance,
            details: [
                'time' => now()->toTimeString(),
                'work_hours' => $attendance->work_hours,
                'type' => $eval['type'],
                'distance' => $eval['distance'],
            ]
        );

        return redirect()->back()->with('success', '下班打卡成功！今日工時已結算。');
    }
}
