<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'unread_notifications_count' => $request->user() ? $request->user()->unreadNotifications()->count() : 0,
                'recent_notifications' => $request->user() ? $request->user()->unreadNotifications()->take(5)->get()->map(function ($n) {
                    return [
                        'id' => $n->id,
                        'title' => $n->data['title'] ?? '系統通知',
                        'message' => $n->data['message'] ?? '',
                        'action_url' => $n->data['action_url'] ?? null,
                        'created_at' => $n->created_at->diffForHumans(),
                    ];
                }) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
