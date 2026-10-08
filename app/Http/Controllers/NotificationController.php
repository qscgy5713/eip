<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * 檢視所有通知中心頁面
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(15)
            ->through(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? 'system',
                    'title' => $notification->data['title'] ?? '系統通知',
                    'message' => $notification->data['message'] ?? '',
                    'action_url' => $notification->data['action_url'] ?? null,
                    'sender_name' => $notification->data['sender_name'] ?? '系統',
                    'read_at' => $notification->read_at ? $notification->read_at->toDateTimeString() : null,
                    'created_at' => $notification->created_at->diffForHumans(),
                    'created_at_full' => $notification->created_at->toDateTimeString(),
                ];
            });

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * 標記單則通知為已讀
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        // 若有 action_url 且為直接點擊跳轉
        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'));
        }

        return back()->with('success', '通知已標記為已讀。');
    }

    /**
     * 全部標記為已讀
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', '所有未讀通知已全數標記為已讀。');
    }

    /**
     * 刪除通知
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->delete();
        }

        return back()->with('success', '通知已刪除。');
    }
}
