<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ApprovalRecord;
use App\Models\FormRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 最新發布公告 (含已讀狀態)
        $announcements = Announcement::with('author')
            ->where('status', 'published')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->take(5)
            ->get()
            ->map(function ($announcement) use ($user) {
                $announcement->is_read = $announcement->isReadBy($user);
                return $announcement;
            });

        // 待我審批的單據
        $pendingApprovals = ApprovalRecord::with(['formRequest.user', 'formRequest.form'])
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->get();

        // 我最近送出的申請單
        $myRequests = FormRequest::with('form')
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'announcements' => $announcements,
            'pendingApprovals' => $pendingApprovals,
            'myRequests' => $myRequests,
            'stats' => [
                'unreadAnnouncementsCount' => Announcement::where('status', 'published')->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))->count(),
                'pendingApprovalsCount' => $pendingApprovals->count(),
                'myPendingRequestsCount' => FormRequest::where('user_id', $user->id)->where('status', 'pending')->count(),
            ],
        ]);
    }
}
