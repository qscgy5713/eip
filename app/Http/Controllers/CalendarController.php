<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\FormRequest;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * 綜合行事曆看板首頁
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $month = $request->input('month', now()->format('Y-m'));

        // 取得月份起訖日期（前後擴展各 7 天以涵蓋月曆的前後補日）
        $startDate = Carbon::parse($month . '-01')->startOfMonth()->subDays(7)->startOfDay();
        $endDate = Carbon::parse($month . '-01')->endOfMonth()->addDays(14)->endOfDay();

        $selectedType = $request->input('type', 'all');
        $selectedDeptId = $request->input('department_id');

        $events = $this->aggregateEvents($user, $startDate, $endDate, $selectedType, $selectedDeptId);
        $departments = Department::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Calendar/Index', [
            'events' => $events,
            'departments' => $departments,
            'filters' => [
                'month' => $month,
                'type' => $selectedType,
                'department_id' => $selectedDeptId,
            ],
        ]);
    }

    /**
     * 聚合所有時間事件（會議室預約、核准請假差勤、企業公告）
     */
    protected function aggregateEvents($user, Carbon $startDate, Carbon $endDate, string $type, ?string $deptId): array
    {
        $events = [];

        // 1. 會議室預約事件
        if ($type === 'all' || $type === 'meeting') {
            $bookingsQuery = RoomBooking::with([
                'room:id,name,location',
                'user:id,name,department_id',
                'attendees:id,name',
            ])
                ->where('status', 'confirmed')
                ->where('start_time', '>=', $startDate)
                ->where('start_time', '<=', $endDate);

            if ($deptId) {
                $bookingsQuery->whereHas('user', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                });
            }

            $bookings = $bookingsQuery->get();

            foreach ($bookings as $b) {
                $events[] = [
                    'id' => 'booking_' . $b->id,
                    'raw_id' => $b->id,
                    'type' => 'meeting',
                    'title' => ($b->room ? $b->room->name . ' · ' : '') . $b->title,
                    'start' => Carbon::parse($b->start_time)->toIso8601String(),
                    'end' => Carbon::parse($b->end_time)->toIso8601String(),
                    'date' => Carbon::parse($b->start_time)->format('Y-m-d'),
                    'user_name' => $b->user?->name ?? '未知同仁',
                    'location' => $b->room ? $b->room->name . ' (' . $b->room->location . ')' : '會議室',
                    'attendees_count' => $b->attendees_count,
                    'attendees' => $b->attendees->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->toArray(),
                    'equipment_needed' => $b->equipment_needed ?? [],
                    'is_mine' => $b->user_id === $user->id,
                    'is_attending' => $b->attendees->contains('id', $user->id),
                    'details' => $b->description,
                ];
            }
        }

        // 2. 核准請假與公出差勤事件
        if ($type === 'all' || $type === 'leave') {
            $leaveFormsQuery = FormRequest::with(['user:id,name,department_id,job_title', 'user.department:id,name', 'form:id,name'])
                ->where('status', 'approved')
                ->whereHas('form', function ($q) {
                    $q->where('name', 'like', '%假%')
                      ->orWhere('name', 'like', '%出%')
                      ->orWhere('name', 'like', '%差%');
                });

            if ($deptId) {
                $leaveFormsQuery->whereHas('user', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                });
            }

            $leaveForms = $leaveFormsQuery->get();

            foreach ($leaveForms as $lf) {
                $data = $lf->data ?? [];
                $formStart = $data['start_date'] ?? null;
                $formEnd = $data['end_date'] ?? $formStart;

                if (!$formStart) {
                    continue;
                }

                $eventStart = Carbon::parse($formStart)->startOfDay();
                $eventEnd = Carbon::parse($formEnd)->endOfDay();

                // 檢查是否在當前時間範圍內
                if ($eventEnd->lt($startDate) || $eventStart->gt($endDate)) {
                    continue;
                }

                $leaveType = $data['leave_type'] ?? ($lf->form?->name ?? '差勤');

                // 展開跨天事件為每日標記或單筆期間標記
                $events[] = [
                    'id' => 'leave_' . $lf->id,
                    'raw_id' => $lf->id,
                    'type' => 'leave',
                    'title' => ($lf->user?->name ?? '同仁') . ' (' . $leaveType . ')',
                    'start' => $eventStart->toIso8601String(),
                    'end' => $eventEnd->toIso8601String(),
                    'date' => $eventStart->format('Y-m-d'),
                    'end_date' => $eventEnd->format('Y-m-d'),
                    'user_name' => $lf->user?->name ?? '未知同仁',
                    'department_name' => $lf->user?->department?->name ?? '一般部門',
                    'leave_type' => $leaveType,
                    'reason' => $data['reason'] ?? '',
                    'days' => $data['days'] ?? 1,
                ];
            }
        }

        // 3. 企業重要公告發布日程
        if ($type === 'all' || $type === 'announcement') {
            $announcementsQuery = Announcement::where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '>=', $startDate)
                ->where('published_at', '<=', $endDate);

            $announcements = $announcementsQuery->get();

            foreach ($announcements as $a) {
                $pubDate = Carbon::parse($a->published_at);
                $events[] = [
                    'id' => 'announcement_' . $a->id,
                    'raw_id' => $a->id,
                    'type' => 'announcement',
                    'title' => '[公告] ' . $a->title,
                    'start' => $pubDate->toIso8601String(),
                    'end' => $pubDate->copy()->addHour()->toIso8601String(),
                    'date' => $pubDate->format('Y-m-d'),
                    'priority' => $a->priority,
                    'category' => $a->category,
                    'is_pinned' => $a->is_pinned,
                ];
            }
        }

        // 依開始時間由近到遠排序
        usort($events, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        return $events;
    }

    /**
     * 匯出指定月份之企業全景行事曆 iCalendar (.ics) 檔案
     */
    public function exportIcs(Request $request)
    {
        $user = $request->user();
        $month = $request->input('month', now()->format('Y-m'));

        $startDate = Carbon::parse($month . '-01')->startOfMonth()->startOfDay();
        $endDate = Carbon::parse($month . '-01')->endOfMonth()->endOfDay();

        $selectedType = $request->input('type', 'all');
        $selectedDeptId = $request->input('department_id');

        $events = $this->aggregateEvents($user, $startDate, $endDate, $selectedType, $selectedDeptId);

        $dtStamp = now()->utc()->format('Ymd\THis\Z');

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//EIP Portal//Corporate Calendar//TW\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:EIP 企業行事曆 ({$month})\r\n";

        foreach ($events as $event) {
            $type = $event['type'];
            $uid = "{$event['id']}@eip.local";

            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:{$uid}\r\n";
            $ics .= "DTSTAMP:{$dtStamp}\r\n";

            if ($type === 'leave') {
                $startDateStr = Carbon::parse($event['date'])->format('Ymd');
                $endDateStr = Carbon::parse($event['end_date'] ?? $event['date'])->addDay()->format('Ymd');
                $ics .= "DTSTART;VALUE=DATE:{$startDateStr}\r\n";
                $ics .= "DTEND;VALUE=DATE:{$endDateStr}\r\n";
            } else {
                $startUtc = Carbon::parse($event['start'])->utc()->format('Ymd\THis\Z');
                $endUtc = Carbon::parse($event['end'])->utc()->format('Ymd\THis\Z');
                $ics .= "DTSTART:{$startUtc}\r\n";
                $ics .= "DTEND:{$endUtc}\r\n";
            }

            $summary = str_replace(["\r", "\n"], ' ', $event['title']);
            $ics .= "SUMMARY:{$summary}\r\n";

            if (!empty($event['location'])) {
                $loc = str_replace(["\r", "\n"], ' ', $event['location']);
                $ics .= "LOCATION:{$loc}\r\n";
            }

            $descParts = [];
            if (!empty($event['user_name'])) {
                $descParts[] = "相關人員：" . $event['user_name'];
            }
            if (!empty($event['details'])) {
                $descParts[] = "說明備註：" . $event['details'];
            }
            if (!empty($event['equipment_needed'])) {
                $descParts[] = "借用設備：" . implode(', ', $event['equipment_needed']);
            }
            if (!empty($descParts)) {
                $ics .= "DESCRIPTION:" . implode('\n', $descParts) . "\r\n";
            }

            $ics .= "STATUS:CONFIRMED\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";

        $fileName = "eip-calendar-{$month}.ics";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
