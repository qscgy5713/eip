<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use App\Services\WebhookService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MeetingRoomController extends Controller
{
    /**
     * 會議室借用首頁 / 今日與指定日期預約狀況
     */
    public function index(Request $request): Response
    {
        $selectedDate = $request->input('date', Carbon::today()->toDateString());
        $startOfDay = Carbon::parse($selectedDate)->startOfDay();
        $endOfDay = Carbon::parse($selectedDate)->endOfDay();

        // 取得所有會議室與該日預約清單
        $rooms = MeetingRoom::query()
            ->when(!$request->user()->isAdmin(), function ($q) {
                $q->where('is_active', true);
            })
            ->with(['bookings' => function ($q) use ($startOfDay, $endOfDay) {
                $q->where('status', 'confirmed')
                    ->where(function ($sub) use ($startOfDay, $endOfDay) {
                        $sub->whereBetween('start_time', [$startOfDay, $endOfDay])
                            ->orWhereBetween('end_time', [$startOfDay, $endOfDay])
                            ->orWhere(function ($overlap) use ($startOfDay, $endOfDay) {
                                $overlap->where('start_time', '<=', $startOfDay)
                                    ->where('end_time', '>=', $endOfDay);
                            });
                    })
                    ->with([
                        'user:id,name,email,department_id',
                        'attendees:id,name,email,department_id',
                    ])
                    ->orderBy('start_time', 'asc');
            }])
            ->orderBy('name', 'asc')
            ->get();

        // 當前使用者未來/近期的有效預約（包含發起與受邀出席）
        $myBookings = RoomBooking::query()
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhereHas('attendees', function ($sub) use ($request) {
                        $sub->where('users.id', $request->user()->id);
                    });
            })
            ->where('end_time', '>=', Carbon::now()->subHours(2))
            ->where('status', 'confirmed')
            ->with([
                'room:id,name,location',
                'user:id,name',
                'attendees:id,name',
            ])
            ->orderBy('start_time', 'asc')
            ->take(10)
            ->get();

        // 所有在職同仁（供預約時搜尋勾選邀請名單）
        $allUsers = User::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'employee_no', 'department_id'])
            ->load('department:id,name');

        return Inertia::render('MeetingRooms/Index', [
            'rooms' => $rooms,
            'selectedDate' => $selectedDate,
            'myBookings' => $myBookings,
            'allUsers' => $allUsers,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    /**
     * 提交會議室預約
     */
    public function storeBooking(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'meeting_room_id' => ['required', 'exists:meeting_rooms,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'attendees_count' => ['nullable', 'integer', 'min:1'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', 'exists:users,id'],
            'equipment_needed' => ['nullable', 'array'],
            'equipment_needed.*' => ['string', 'max:50'],
        ]);

        $room = MeetingRoom::findOrFail($validated['meeting_room_id']);

        if (!$room->is_active) {
            throw ValidationException::withMessages([
                'meeting_room_id' => '該會議室目前處於非開放/維護狀態，無法預約。',
            ]);
        }

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);

        // 預計與會同仁名單（排除發起人自己重複加入）
        $attendeeIds = array_values(array_diff($validated['attendee_ids'] ?? [], [$request->user()->id]));
        $calculatedAttendeesCount = max($validated['attendees_count'] ?? 1, count($attendeeIds) + 1);

        // 檢查與會人數不可超出會議室容納上限
        if ($calculatedAttendeesCount > $room->capacity) {
            throw ValidationException::withMessages([
                'attendees_count' => "預計與會人數 ({$calculatedAttendeesCount} 人) 已超過此會議室最大容納上限 ({$room->capacity} 人)。",
            ]);
        }

        // 防止預約過去時間
        if ($endTime->isPast()) {
            throw ValidationException::withMessages([
                'start_time' => '預約結束時間不可早於當前時間。',
            ]);
        }

        // 防衝突排他性校驗
        if (RoomBooking::hasConflict($room->id, $startTime, $endTime)) {
            throw ValidationException::withMessages([
                'start_time' => '所選時段已被其他同仁預約借用，請重新調整時段或更換會議室。',
            ]);
        }

        $booking = RoomBooking::create([
            'meeting_room_id' => $room->id,
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'attendees_count' => $calculatedAttendeesCount,
            'equipment_needed' => $validated['equipment_needed'] ?? [],
            'status' => 'confirmed',
        ]);

        // 同步受邀與會同仁
        if (!empty($attendeeIds)) {
            $booking->attendees()->sync($attendeeIds);
        }

        // 記錄審計日誌
        AuditLog::log(
            action: 'book_meeting_room',
            description: "預約了會議室「{$room->name}」（{$validated['title']}）",
            auditable: $booking,
            details: [
                'room' => $room->name,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'attendees_count' => $calculatedAttendeesCount,
                'invited_count' => count($attendeeIds),
                'equipment_needed' => $validated['equipment_needed'] ?? [],
            ]
        );

        // 發送預約成功通知給借用同仁
        $request->user()->notify(new EipSystemNotification(
            title: "【會議室借用確認】{$room->name}",
            message: "您已成功預約「{$room->name}」（時間：{$startTime->format('m/d H:i')} ~ {$endTime->format('H:i')}，主旨：{$validated['title']}）。",
            type: 'meeting_room',
            actionUrl: route('meeting-rooms.index', ['date' => $startTime->toDateString()]),
            senderName: '系統管理員'
        ));

        // 發送會議邀請通知給所有受邀同仁
        if (!empty($attendeeIds)) {
            $invitedUsers = User::whereIn('id', $attendeeIds)->get();
            foreach ($invitedUsers as $invitee) {
                $invitee->notify(new EipSystemNotification(
                    title: "【會議邀請】{$validated['title']}",
                    message: "{$request->user()->name} 邀請您參加於「{$room->name}」之會議（時間：{$startTime->format('m/d H:i')} ~ {$endTime->format('H:i')}）。",
                    type: 'meeting_room',
                    actionUrl: route('meeting-rooms.index', ['date' => $startTime->toDateString()]),
                    senderName: $request->user()->name,
                    extra: ['booking_id' => $booking->id]
                ));
            }
        }

        // 觸發外部生態 Webhook 事件
        WebhookService::dispatch(
            'room.booked',
            [
                'booking_id' => $booking->id,
                'room' => $room->name,
                'title' => $booking->title,
                'user' => $request->user()->name,
                'attendees_count' => $calculatedAttendeesCount,
                'equipment_needed' => $validated['equipment_needed'] ?? [],
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
            ],
            "【會議室借用】{$request->user()->name} 預約了「{$room->name}」（{$booking->title}）"
        );

        return back()->with('success', "已成功預約「{$room->name}」！");
    }

    /**
     * 取消會議室預約
     */
    public function cancelBooking(Request $request, RoomBooking $booking): RedirectResponse
    {
        // 只有預約人本人或管理員有權取消
        if ($booking->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403, '您沒有權限取消此會議預約。');
        }

        if ($booking->status === 'cancelled') {
            return back()->with('error', '該預約先前已被取消。');
        }

        $reason = $request->input('reason', '同仁自行取消');
        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        // 取得受邀同仁並連鎖發送取消通知
        $attendees = $booking->attendees()->get();
        $roomName = $booking->room?->name ?? '會議室';

        foreach ($attendees as $attendee) {
            if ($attendee->id !== $request->user()->id) {
                $attendee->notify(new EipSystemNotification(
                    title: "【會議取消通知】{$booking->title}",
                    message: "原定於「{$roomName}」之會議「{$booking->title}」已取消（原因：{$reason}）。",
                    type: 'meeting_room',
                    actionUrl: route('meeting-rooms.index', ['date' => Carbon::parse($booking->start_time)->toDateString()]),
                    senderName: $request->user()->name,
                    extra: ['booking_id' => $booking->id]
                ));
            }
        }

        // 記錄審計日誌
        AuditLog::log(
            action: 'cancel_room_booking',
            description: "取消了會議室「{$roomName}」預約（原主旨：{$booking->title}）",
            auditable: $booking,
            details: ['reason' => $reason]
        );

        return back()->with('success', '已成功取消會議預約。');
    }

    /**
     * 新增會議室 (管理員)
     */
    public function storeRoom(Request $request): RedirectResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, '僅系統管理員具備維護會議室權限。');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        MeetingRoom::create([
            'name' => $validated['name'],
            'location' => $validated['location'],
            'capacity' => $validated['capacity'],
            'equipment' => $validated['equipment'] ?? [],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', "已成功新增會議室「{$validated['name']}」！");
    }

    /**
     * 更新會議室 (管理員)
     */
    public function updateRoom(Request $request, MeetingRoom $meetingRoom): RedirectResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, '僅系統管理員具備維護會議室權限。');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $meetingRoom->update($validated);

        return back()->with('success', "會議室「{$meetingRoom->name}」資料已更新！");
    }

    /**
     * 匯出單一會議之 iCalendar (.ics) 檔案
     */
    public function exportIcs(Request $request, RoomBooking $booking)
    {
        $booking->load(['room', 'user', 'attendees']);

        $dtStamp = now()->utc()->format('Ymd\THis\Z');
        $dtStart = Carbon::parse($booking->start_time)->utc()->format('Ymd\THis\Z');
        $dtEnd = Carbon::parse($booking->end_time)->utc()->format('Ymd\THis\Z');

        $roomName = $booking->room?->name ?? '會議室';
        $location = $booking->room ? "{$booking->room->location} ({$booking->room->name})" : '會議室';
        $summary = "{$roomName} · {$booking->title}";

        $descriptionLines = [
            "會議主旨：{$booking->title}",
            "發起人：{$booking->user?->name}",
            "預約會議室：{$roomName}",
            "與會總人數：{$booking->attendees_count} 人",
        ];

        if (!empty($booking->equipment_needed)) {
            $descriptionLines[] = "借用設備：" . implode(', ', $booking->equipment_needed);
        }

        if ($booking->description) {
            $descriptionLines[] = "備註說明：{$booking->description}";
        }

        $description = implode('\n', $descriptionLines);

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//EIP Portal//Meeting Rooms//TW\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:booking-{$booking->id}@eip.local\r\n";
        $ics .= "DTSTAMP:{$dtStamp}\r\n";
        $ics .= "DTSTART:{$dtStart}\r\n";
        $ics .= "DTEND:{$dtEnd}\r\n";
        $ics .= "SUMMARY:{$summary}\r\n";
        $ics .= "DESCRIPTION:{$description}\r\n";
        $ics .= "LOCATION:{$location}\r\n";
        if ($booking->user) {
            $ics .= "ORGANIZER;CN={$booking->user->name}:mailto:{$booking->user->email}\r\n";
        }
        foreach ($booking->attendees as $att) {
            $ics .= "ATTENDEE;ROLE=REQ-PARTICIPANT;CN={$att->name}:mailto:{$att->email}\r\n";
        }
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        $fileName = "meeting-{$booking->id}.ics";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
