<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ApprovalRecord;
use App\Models\Attendance;
use App\Models\FormRequest;
use App\Models\Poll;
use App\Models\PollVoter;
use App\Models\RoomBooking;
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

        // 2. 待我審批的單據 (包含我代理之主管單據)
        $delegatorIds = $user->delegatedToMe()->currentlyActive()->pluck('user_id');
        $validApproverIds = collect([$user->id])->merge($delegatorIds)->unique();

        $pendingApprovals = ApprovalRecord::with(['formRequest.user', 'formRequest.form', 'approver'])
            ->whereIn('approver_id', $validApproverIds)
            ->where('status', 'pending')
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($record) use ($user) {
                $record->is_delegated = $record->approver_id !== $user->id;
                return $record;
            });

        $totalPendingApprovalsCount = ApprovalRecord::whereIn('approver_id', $validApproverIds)
            ->where('status', 'pending')
            ->count();

        // 3. 我最近送出的申請單
        $myRequests = FormRequest::with('form')
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        // 4. 今日打卡狀態
        $today = now()->toDateString();
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        // 5. 即將開始的會議行程 (包含本人發起與受邀出席)
        $myUpcomingBookings = RoomBooking::with(['room', 'user:id,name', 'attendees:id,name'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('attendees', function ($sub) use ($user) {
                      $sub->where('users.id', $user->id);
                  });
            })
            ->where('status', 'confirmed')
            ->where('end_time', '>=', now())
            ->orderBy('start_time')
            ->take(3)
            ->get()
            ->map(function ($booking) use ($user) {
                $booking->is_host = $booking->user_id === $user->id;
                return $booking;
            });

        // 6. 個人休假額度摘要 (特休與補休可用餘額)
        $leaveBalances = app(\App\Services\LeaveBalanceService::class)->getUserBalances($user);
        $annualBalance = $leaveBalances->firstWhere('leave_type', 'annual');
        $compBalance = $leaveBalances->firstWhere('leave_type', 'compensatory');
        $myLeaveSummary = [
            'annual_available' => $annualBalance ? (float) $annualBalance->available_days : 0.0,
            'compensatory_available' => $compBalance ? (float) $compBalance->available_days : 0.0,
        ];

        // 7. 部門團隊今日出勤快報 (主管 / 管理員專屬)
        $teamAttendanceSnapshot = null;
        if ($user->isManager() || $user->isAdmin() || $user->isHr()) {
            $teamQuery = \App\Models\User::where('status', 'active');
            if ($user->isManager() && !$user->isAdmin()) {
                $teamQuery->where('department_id', $user->department_id);
            }
            $teamUserIds = $teamQuery->pluck('id');
            $teamTotalCount = $teamUserIds->count();

            $todayTeamAttendances = Attendance::whereIn('user_id', $teamUserIds)
                ->whereDate('date', $today)
                ->get();

            $clockedInCount = $todayTeamAttendances->whereNotNull('clock_in_at')->count();

            // 今日核准請假中的同仁數 (相容 SQLite/PostgreSQL 記憶體安全過濾)
            $onLeaveUserIds = FormRequest::whereIn('user_id', $teamUserIds)
                ->where('status', 'approved')
                ->whereHas('form', fn($q) => $q->where('code', 'LEAVE'))
                ->get()
                ->filter(function ($req) use ($today) {
                    $data = $req->data ?? [];
                    $start = $data['start_date'] ?? null;
                    $end = $data['end_date'] ?? $start;
                    return $start && $end && $start <= $today && $end >= $today;
                })
                ->pluck('user_id')
                ->unique();
            $onLeaveCount = $onLeaveUserIds->count();

            $teamAttendanceSnapshot = [
                'total_members' => $teamTotalCount,
                'clocked_in_count' => $clockedInCount,
                'on_leave_count' => $onLeaveCount,
                'unclocked_count' => max(0, $teamTotalCount - $clockedInCount - $onLeaveCount),
            ];
        }

        // 進行中的企業投票活動
        $activePolls = Poll::withCount(['voters'])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->take(3)
            ->get();

        $votedPollIds = PollVoter::where('user_id', $user->id)
            ->whereIn('poll_id', $activePolls->pluck('id'))
            ->pluck('poll_id')
            ->toArray();

        $activePolls->transform(function ($poll) use ($votedPollIds) {
            $poll->has_voted = in_array($poll->id, $votedPollIds);
            return $poll;
        });

        // 9. 我的進行中專案任務
        $myTasks = \App\Models\Task::with(['project:id,code,name'])
            ->where('assignee_id', $user->id)
            ->whereIn('status', ['todo', 'in_progress', 'review'])
            ->orderByRaw("CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 0 ELSE 1 END", [now()->toDateString()])
            ->orderBy('due_date')
            ->take(4)
            ->get()
            ->map(function ($task) {
                $task->is_overdue = $task->isOverdue();
                return $task;
            });

        // 10. 我保管/借用的公務資產
        $myAssets = \App\Models\Asset::with(['department:id,name', 'activeLoan'])
            ->where('current_holder_id', $user->id)
            ->where('status', 'in_use')
            ->take(4)
            ->get()
            ->map(function ($asset) {
                $asset->is_overdue = $asset->activeLoan?->isOverdue() ?? false;
                $asset->expected_return_date = $asset->activeLoan?->expected_return_date?->toDateString();
                return $asset;
            });

        // 11. 本週工作週報狀態
        $now = \Carbon\Carbon::now();
        $currentYear = $now->year;
        $currentWeek = $now->isoWeek();
        $myWeeklyReport = \App\Models\WeeklyReport::where('user_id', $user->id)
            ->where('year', $currentYear)
            ->where('week_number', $currentWeek)
            ->first();

        return Inertia::render('Dashboard', [
            'announcements' => $announcements,
            'pendingApprovals' => $pendingApprovals,
            'myRequests' => $myRequests,
            'todayAttendance' => $todayAttendance,
            'myUpcomingBookings' => $myUpcomingBookings,
            'myLeaveSummary' => $myLeaveSummary,
            'teamAttendanceSnapshot' => $teamAttendanceSnapshot,
            'activePolls' => $activePolls,
            'myTasks' => $myTasks,
            'myAssets' => $myAssets,
            'myWeeklyReport' => $myWeeklyReport,
            'currentWeekNumber' => $currentWeek,
            'stats' => [
                'unreadAnnouncementsCount' => Announcement::where('status', 'published')->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))->count(),
                'pendingApprovalsCount' => $totalPendingApprovalsCount,
                'myPendingRequestsCount' => FormRequest::where('user_id', $user->id)->where('status', 'pending')->count(),
                'myPendingTasksCount' => \App\Models\Task::where('assignee_id', $user->id)->whereIn('status', ['todo', 'in_progress', 'review'])->count(),
                'myHeldAssetsCount' => \App\Models\Asset::where('current_holder_id', $user->id)->count(),
                'isWeeklyReportSubmitted' => $myWeeklyReport && $myWeeklyReport->status !== 'draft',
            ],
        ]);
    }
}
