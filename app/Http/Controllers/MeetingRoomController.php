<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Notifications\EipSystemNotification;
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
                    ->with('user:id,name,email,department_id')
                    ->orderBy('start_time', 'asc');
            }])
            ->orderBy('name', 'asc')
            ->get();

        // 當前使用者未來/近期的有效預約（個人行程卡）
        $myBookings = RoomBooking::query()
            ->where('user_id', $request->user()->id)
            ->where('end_time', '>=', Carbon::now()->subHours(2))
            ->where('status', 'confirmed')
            ->with('room:id,name,location')
            ->orderBy('start_time', 'asc')
            ->take(10)
            ->get();

        return Inertia::render('MeetingRooms/Index', [
            'rooms' => $rooms,
            'selectedDate' => $selectedDate,
            'myBookings' => $myBookings,
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
        ]);

        $room = MeetingRoom::findOrFail($validated['meeting_room_id']);

        if (!$room->is_active) {
            throw ValidationException::withMessages([
                'meeting_room_id' => '該會議室目前處於非開放/維護狀態，無法預約。',
            ]);
        }

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);

        // 檢查與會人數不可超出會議室容納上限
        if (!empty($validated['attendees_count']) && $validated['attendees_count'] > $room->capacity) {
            throw ValidationException::withMessages([
                'attendees_count' => "預估與會人數 ({$validated['attendees_count']} 人) 已超過此會議室最大容納上限 ({$room->capacity} 人)。",
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
            'attendees_count' => $validated['attendees_count'] ?? 1,
            'status' => 'confirmed',
        ]);

        // 記錄審計日誌
        AuditLog::log(
            action: 'book_meeting_room',
            description: "預約了會議室「{$room->name}」（{$validated['title']}）",
            auditable: $booking,
            details: [
                'room' => $room->name,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
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

        // 記錄審計日誌
        AuditLog::log(
            action: 'cancel_room_booking',
            description: "取消了會議室「{$booking->meetingRoom?->name}」預約（原主旨：{$booking->title}）",
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
}
