<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use App\Models\User;
use App\Models\VisitorReservation;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorReservationController extends Controller
{
    /**
     * 訪客預約總表與前台接待清單
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        $query = VisitorReservation::with(['host.department', 'meetingRoom:id,name']);

        // 權限過濾：若非 admin/manager，預設查看 mine，也可切換到 all（查看公開訪客排程）
        $scope = $request->input('scope', ($user->isAdmin() || $user->isManager()) ? 'all' : 'mine');
        if ($scope === 'mine') {
            $query->where('host_id', $user->id);
        }

        // 狀態篩選
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 日期篩選
        if ($request->filled('visit_date')) {
            $query->whereDate('visit_date', $request->visit_date);
        }

        // 關鍵字搜尋（貴賓姓名、所屬單位、通行代碼、門禁卡號）
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('visitor_name', 'like', "%{$search}%")
                    ->orWhere('visitor_company', 'like', "%{$search}%")
                    ->orWhere('reservation_code', 'like', "%{$search}%")
                    ->orWhere('badge_number', 'like', "%{$search}%");
            });
        }

        // 今日數據統計指標
        $todayQuery = VisitorReservation::whereDate('visit_date', $today);
        $metrics = [
            'total_today' => (clone $todayQuery)->count(),
            'expected_today' => (clone $todayQuery)->where('status', 'expected')->count(),
            'checked_in_today' => (clone $todayQuery)->where('status', 'checked_in')->count(),
            'checked_out_today' => (clone $todayQuery)->where('status', 'checked_out')->count(),
        ];

        $reservations = $query->orderBy('visit_date', 'desc')
            ->orderBy('expected_arrival_time', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('VisitorReservations/Index', [
            'reservations' => $reservations,
            'metrics' => $metrics,
            'filters' => [
                'status' => $request->input('status', ''),
                'visit_date' => $request->input('visit_date', ''),
                'search' => $request->input('search', ''),
                'scope' => $scope,
            ],
            'canManageAll' => $user->isAdmin() || $user->isManager(),
        ]);
    }

    /**
     * 發起訪客預約表單
     */
    public function create(Request $request): Response
    {
        $meetingRooms = MeetingRoom::where('is_active', true)
            ->select('id', 'name', 'capacity', 'location')
            ->get();

        $hosts = [];
        if ($request->user()->isAdmin()) {
            $hosts = User::select('id', 'name', 'department_id')->with('department:id,name')->orderBy('name')->get();
        }

        return Inertia::render('VisitorReservations/Create', [
            'meetingRooms' => $meetingRooms,
            'hosts' => $hosts,
        ]);
    }

    /**
     * 儲存訪客預約
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visitor_name' => ['required', 'string', 'max:100'],
            'visitor_company' => ['nullable', 'string', 'max:150'],
            'visitor_email' => ['nullable', 'email', 'max:150'],
            'visitor_phone' => ['nullable', 'string', 'max:50'],
            'visit_date' => ['required', 'date'],
            'expected_arrival_time' => ['nullable', 'string', 'max:20'],
            'purpose' => ['required', 'string', 'in:business,interview,audit,vendor,other'],
            'meeting_room_id' => ['nullable', 'integer', 'exists:meeting_rooms,id'],
            'visitor_count' => ['required', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'host_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $hostId = ($request->user()->isAdmin() && ! empty($validated['host_id']))
            ? $validated['host_id']
            : $request->user()->id;

        $reservation = VisitorReservation::create([
            'reservation_code' => VisitorReservation::generateCode(),
            'host_id' => $hostId,
            'visitor_name' => $validated['visitor_name'],
            'visitor_company' => $validated['visitor_company'] ?? null,
            'visitor_email' => $validated['visitor_email'] ?? null,
            'visitor_phone' => $validated['visitor_phone'] ?? null,
            'visit_date' => $validated['visit_date'],
            'expected_arrival_time' => $validated['expected_arrival_time'] ?? null,
            'purpose' => $validated['purpose'],
            'meeting_room_id' => $validated['meeting_room_id'] ?? null,
            'visitor_count' => $validated['visitor_count'],
            'status' => 'expected',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('visitor-reservations.show', $reservation)
            ->with('success', "訪客預約建立成功！通行碼：{$reservation->reservation_code}");
    }

    /**
     * 檢視訪客通行證與會談詳情
     */
    public function show(Request $request, VisitorReservation $visitorReservation): Response
    {
        $visitorReservation->load(['host.department', 'meetingRoom']);

        return Inertia::render('VisitorReservations/Show', [
            'reservation' => $visitorReservation,
            'canManage' => $visitorReservation->canManage($request->user()),
        ]);
    }

    /**
     * 前台訪客簽到登記 (Check-in) 並即時推播通知接待同仁
     */
    public function checkIn(Request $request, VisitorReservation $visitorReservation): RedirectResponse
    {
        $validated = $request->validate([
            'badge_number' => ['nullable', 'string', 'max:50'],
        ]);

        $visitorReservation->update([
            'status' => 'checked_in',
            'actual_check_in_at' => now(),
            'badge_number' => $validated['badge_number'] ?? $visitorReservation->badge_number,
        ]);

        // 連鎖即時推播通知接待員工
        $host = $visitorReservation->host;
        if ($host) {
            $companyText = $visitorReservation->visitor_company ? "({$visitorReservation->visitor_company})" : '';
            $host->notify(new EipSystemNotification(
                title: '【訪客已簽到】貴賓抵達大廳通知',
                message: "您接待的貴賓 {$visitorReservation->visitor_name} {$companyText} 已完成簽到登記，請前往大廳或會客室接待！",
                type: 'visitor',
                actionUrl: route('visitor-reservations.show', $visitorReservation),
                senderName: '前台大廳系統'
            ));
        }

        return back()->with('success', '訪客已順利完成簽到並通知接待同仁！');
    }

    /**
     * 訪客離廠簽退 (Check-out)
     */
    public function checkOut(VisitorReservation $visitorReservation): RedirectResponse
    {
        $visitorReservation->update([
            'status' => 'checked_out',
            'actual_check_out_at' => now(),
        ]);

        return back()->with('success', '訪客已完成離廠簽退手續。');
    }

    /**
     * 取消訪客預約
     */
    public function cancel(Request $request, VisitorReservation $visitorReservation): RedirectResponse
    {
        if (! $visitorReservation->canManage($request->user())) {
            abort(403, '您沒有權限取消此訪客預約');
        }

        $visitorReservation->update([
            'status' => 'cancelled',
        ]);

        return back()->with('success', '訪客預約已取消。');
    }

    /**
     * 符合 ISO 27001 實體安全訪客出入紀錄 CSV 匯出
     */
    public function exportLog(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, '僅限系統管理員或主管可匯出訪客紀錄。');
        }

        $fileName = 'visitor_logs_' . date('Ymd_His') . '.csv';

        $query = VisitorReservation::with(['host.department', 'meetingRoom:id,name']);

        if ($request->filled('start_date')) {
            $query->whereDate('visit_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('visit_date', '<=', $request->end_date);
        }

        $records = $query->orderBy('visit_date', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($records) {
            $output = fopen('php://output', 'w');

            // 寫入 UTF-8 BOM 防中文亂碼
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // 標題列
            fputcsv($output, [
                '通行代碼',
                '預約日期',
                '貴賓姓名',
                '來訪單位/公司',
                '聯絡電話',
                '來訪人數',
                '來訪事由',
                '預約會議室',
                '接待員工',
                '接待部門',
                '狀態',
                '簽到時間',
                '簽退時間',
                '門禁卡號',
                '接待備註',
            ]);

            foreach ($records as $item) {
                // 防 CSV Formula Injection 跳脫處理
                $sanitize = function (?string $val) {
                    if ($val === null) return '';
                    if (str_starts_with($val, '=') || str_starts_with($val, '+') || str_starts_with($val, '-') || str_starts_with($val, '@')) {
                        return "'" . $val;
                    }
                    return $val;
                };

                fputcsv($output, [
                    $sanitize($item->reservation_code),
                    $item->visit_date ? $item->visit_date->format('Y-m-d') : '',
                    $sanitize($item->visitor_name),
                    $sanitize($item->visitor_company),
                    $sanitize($item->visitor_phone),
                    $item->visitor_count,
                    $item->purpose_label,
                    $item->meetingRoom?->name ?? '無',
                    $item->host?->name ?? '未知',
                    $item->host?->department?->name ?? '',
                    $item->status_label,
                    $item->actual_check_in_at ? $item->actual_check_in_at->format('Y-m-d H:i:s') : '尚未簽到',
                    $item->actual_check_out_at ? $item->actual_check_out_at->format('Y-m-d H:i:s') : '尚未簽退',
                    $sanitize($item->badge_number),
                    $sanitize($item->notes),
                ]);
            }

            fclose($output);
        }, 200, $headers);
    }
}
