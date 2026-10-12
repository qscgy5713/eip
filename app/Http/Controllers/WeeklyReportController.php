<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Models\WeeklyReportComment;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WeeklyReportController extends Controller
{
    /**
     * 週報清單列表與統計總覽
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now();
        $currentYear = (int) $request->input('year', $now->year);
        $currentWeek = (int) $request->input('week', $now->isoWeek());

        // 計算該週週一與週日
        $dateInWeek = Carbon::now()->setISODate($currentYear, $currentWeek);
        $weekStart = $dateInWeek->copy()->startOfWeek()->format('Y-m-d');
        $weekEnd = $dateInWeek->copy()->endOfWeek()->format('Y-m-d');

        $view = $request->input('view', 'my'); // 'my' 或 'team'
        $canManage = $user->isManager() || $user->isAdmin();

        // 我當週是否已繳交週報
        $myCurrentWeekReport = WeeklyReport::where('user_id', $user->id)
            ->where('year', $currentYear)
            ->where('week_number', $currentWeek)
            ->first();

        // 查詢列表
        $query = WeeklyReport::query()->with(['user:id,name,email,job_title', 'department:id,name']);

        if ($view === 'team' && $canManage) {
            if ($user->isAdmin()) {
                if ($request->filled('department_id')) {
                    $query->where('department_id', $request->department_id);
                }
            } else {
                // 部門主管限制同部門同仁
                $query->where('department_id', $user->department_id);
            }

            if ($request->filled('week')) {
                $query->where('year', $currentYear)->where('week_number', $currentWeek);
            }
        } else {
            // 我的週報視圖
            $view = 'my';
            $query->where('user_id', $user->id);
            if ($request->filled('year')) {
                $query->where('year', $currentYear);
            }
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('summary', 'like', "%{$search}%")
                  ->orWhere('achievements', 'like', "%{$search}%")
                  ->orWhere('plans_next_week', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $reports = $query->orderByDesc('year')
            ->orderByDesc('week_number')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // 部門團隊統計 (針對主管/管理員)
        $teamStats = null;
        if ($canManage) {
            $deptUserQuery = User::where('status', 'active');
            if (! $user->isAdmin()) {
                $deptUserQuery->where('department_id', $user->department_id);
            } elseif ($request->filled('department_id')) {
                $deptUserQuery->where('department_id', $request->department_id);
            }

            $deptUsers = $deptUserQuery->get(['id', 'name', 'email', 'job_title', 'department_id']);
            $submittedUserIds = WeeklyReport::where('year', $currentYear)
                ->where('week_number', $currentWeek)
                ->where('status', '!=', 'draft')
                ->whereIn('user_id', $deptUsers->pluck('id'))
                ->pluck('user_id')
                ->toArray();

            $unsubmittedUsers = $deptUsers->filter(fn ($u) => ! in_array($u->id, $submittedUserIds))->values();

            $teamStats = [
                'total_members' => $deptUsers->count(),
                'submitted_count' => count($submittedUserIds),
                'unsubmitted_count' => $unsubmittedUsers->count(),
                'unsubmitted_users' => $unsubmittedUsers,
            ];
        }

        $departments = $user->isAdmin() ? Department::orderBy('name')->get(['id', 'name']) : [];

        return Inertia::render('WeeklyReports/Index', [
            'reports' => $reports,
            'myCurrentWeekReport' => $myCurrentWeekReport,
            'currentWeekInfo' => [
                'year' => $currentYear,
                'week_number' => $currentWeek,
                'start_date' => $weekStart,
                'end_date' => $weekEnd,
                'is_current_week' => ($currentYear === $now->year && $currentWeek === $now->isoWeek()),
            ],
            'teamStats' => $teamStats,
            'departments' => $departments,
            'canManage' => $canManage,
            'filters' => [
                'view' => $view,
                'year' => $currentYear,
                'week' => $currentWeek,
                'status' => $request->status ?? 'all',
                'search' => $request->search ?? '',
                'department_id' => $request->department_id ?? '',
            ],
        ]);
    }

    /**
     * 建立週報頁面
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now();
        $year = (int) $request->input('year', $now->year);
        $week = (int) $request->input('week', $now->isoWeek());

        // 檢查是否已存在
        $existing = WeeklyReport::where('user_id', $user->id)
            ->where('year', $year)
            ->where('week_number', $week)
            ->first();

        if ($existing) {
            return redirect()->route('weekly-reports.show', $existing->id)
                ->with('info', "第 {$year} 年第 {$week} 週之週報已存在！");
        }

        $dateInWeek = Carbon::now()->setISODate($year, $week);
        $weekStart = $dateInWeek->copy()->startOfWeek()->format('Y-m-d');
        $weekEnd = $dateInWeek->copy()->endOfWeek()->format('Y-m-d');

        // 抓取該同仁在該週期間標記為 Done 的任務
        $startDt = Carbon::parse($weekStart)->startOfDay();
        $endDt = Carbon::parse($weekEnd)->endOfDay();

        $completedTasks = Task::where('assignee_id', $user->id)
            ->where('status', 'done')
            ->whereBetween('completed_at', [$startDt, $endDt])
            ->with('project:id,code,name')
            ->get(['id', 'project_id', 'title', 'completed_at', 'actual_hours']);

        return Inertia::render('WeeklyReports/Create', [
            'weekInfo' => [
                'year' => $year,
                'week_number' => $week,
                'start_date' => $weekStart,
                'end_date' => $weekEnd,
            ],
            'completedTasks' => $completedTasks,
        ]);
    }

    /**
     * 儲存週報
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'year' => ['required', 'integer'],
            'week_number' => ['required', 'integer', 'min:1', 'max:53'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'summary' => ['required', 'string', 'max:1000'],
            'achievements' => ['required', 'string'],
            'plans_next_week' => ['required', 'string'],
            'challenges_or_support' => ['nullable', 'string'],
            'is_draft' => ['nullable', 'boolean'],
        ]);

        $isDraft = (bool) ($validated['is_draft'] ?? false);
        $status = $isDraft ? 'draft' : 'submitted';

        // 避免重複建立
        $report = WeeklyReport::where('user_id', $user->id)
            ->where('year', $validated['year'])
            ->where('week_number', $validated['week_number'])
            ->first();

        if ($report) {
            return back()->withErrors(['error' => "您已建立過 {$validated['year']} 年第 {$validated['week_number']} 週之週報！"]);
        }

        $report = WeeklyReport::create([
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'year' => $validated['year'],
            'week_number' => $validated['week_number'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'summary' => $validated['summary'],
            'achievements' => $validated['achievements'],
            'plans_next_week' => $validated['plans_next_week'],
            'challenges_or_support' => $validated['challenges_or_support'] ?? null,
            'status' => $status,
            'submitted_at' => $isDraft ? null : now(),
        ]);

        // 發送通知給主管 (非草稿時)
        if (! $isDraft && $user->department && $user->department->leader_id && $user->department->leader_id !== $user->id) {
            $leader = User::find($user->department->leader_id);
            if ($leader) {
                $leader->notify(new EipSystemNotification(
                    title: '同仁工作週報提交通知',
                    message: "{$user->name} 已提交 {$report->year} 年第 {$report->week_number} 週工作週報",
                    type: 'weekly_report',
                    actionUrl: route('weekly-reports.show', $report->id),
                    senderName: $user->name,
                    extra: ['report_id' => $report->id]
                ));
            }
        }

        AuditLog::log(
            'weekly_report_submit',
            ($isDraft ? '暫存週報草稿' : '正式提交工作週報') . " [{$report->year} W{$report->week_number}]",
            $report,
            ['report_id' => $report->id, 'year' => $report->year, 'week_number' => $report->week_number, 'status' => $status]
        );

        return redirect()->route('weekly-reports.show', $report->id)
            ->with('success', $isDraft ? '週報已暫存為草稿！' : '週報已成功送出！');
    }

    /**
     * 週報詳情與點評互動
     */
    public function show(WeeklyReport $weeklyReport, Request $request)
    {
        $user = $request->user();

        if (! $weeklyReport->canAccess($user)) {
            abort(403, '您沒有權限調閱此週報');
        }

        $weeklyReport->load([
            'user:id,name,email,job_title,department_id',
            'department:id,name',
            'comments' => function ($q) {
                $q->with('user:id,name,email,role')->orderBy('created_at', 'asc');
            },
        ]);

        return Inertia::render('WeeklyReports/Show', [
            'report' => $weeklyReport,
            'canEdit' => $weeklyReport->canEdit($user),
            'canManage' => $user->isManager() || $user->isAdmin(),
        ]);
    }

    /**
     * 編輯週報頁面
     */
    public function edit(WeeklyReport $weeklyReport, Request $request)
    {
        $user = $request->user();

        if (! $weeklyReport->canEdit($user)) {
            abort(403, '此週報狀態無法編輯或您沒有編輯權限');
        }

        return Inertia::render('WeeklyReports/Edit', [
            'report' => $weeklyReport,
        ]);
    }

    /**
     * 更新週報內容
     */
    public function update(Request $request, WeeklyReport $weeklyReport)
    {
        $user = $request->user();

        if (! $weeklyReport->canEdit($user)) {
            abort(403, '此週報狀態無法編輯或您沒有編輯權限');
        }

        $validated = $request->validate([
            'summary' => ['required', 'string', 'max:1000'],
            'achievements' => ['required', 'string'],
            'plans_next_week' => ['required', 'string'],
            'challenges_or_support' => ['nullable', 'string'],
            'is_draft' => ['nullable', 'boolean'],
        ]);

        $isDraft = (bool) ($validated['is_draft'] ?? false);
        $wasDraft = $weeklyReport->status === 'draft';
        $status = $isDraft ? 'draft' : 'submitted';

        $updateData = [
            'summary' => $validated['summary'],
            'achievements' => $validated['achievements'],
            'plans_next_week' => $validated['plans_next_week'],
            'challenges_or_support' => $validated['challenges_or_support'] ?? null,
            'status' => $status,
        ];

        if ($wasDraft && ! $isDraft) {
            $updateData['submitted_at'] = now();
        }

        $weeklyReport->update($updateData);

        // 若由草稿轉提交，通知主管
        if ($wasDraft && ! $isDraft && $user->department && $user->department->leader_id && $user->department->leader_id !== $user->id) {
            $leader = User::find($user->department->leader_id);
            if ($leader) {
                $leader->notify(new EipSystemNotification(
                    title: '同仁工作週報提交通知',
                    message: "{$user->name} 已正式提交 {$weeklyReport->year} 年第 {$weeklyReport->week_number} 週工作週報",
                    type: 'weekly_report',
                    actionUrl: route('weekly-reports.show', $weeklyReport->id),
                    senderName: $user->name,
                    extra: ['report_id' => $weeklyReport->id]
                ));
            }
        }

        AuditLog::log(
            'weekly_report_update',
            "更新週報 [{$weeklyReport->year} W{$weeklyReport->week_number}]",
            $weeklyReport,
            ['report_id' => $weeklyReport->id, 'status' => $status]
        );

        return redirect()->route('weekly-reports.show', $weeklyReport->id)
            ->with('success', '週報已更新！');
    }

    /**
     * 刪除週報
     */
    public function destroy(WeeklyReport $weeklyReport, Request $request)
    {
        $user = $request->user();

        if (! $weeklyReport->canEdit($user) && ! $user->isAdmin()) {
            abort(403, '您沒有權限刪除此週報');
        }

        $year = $weeklyReport->year;
        $week = $weeklyReport->week_number;

        AuditLog::log(
            'weekly_report_delete',
            "刪除週報 [{$year} W{$week}]",
            $weeklyReport,
            ['year' => $year, 'week_number' => $week]
        );

        $weeklyReport->delete();

        return redirect()->route('weekly-reports.index')
            ->with('success', "第 {$year} 年第 {$week} 週週報已刪除");
    }

    /**
     * 主管指導點評或同仁回覆
     */
    public function comment(Request $request, WeeklyReport $weeklyReport)
    {
        $user = $request->user();

        if (! $weeklyReport->canAccess($user)) {
            abort(403, '您沒有權限點評此週報');
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $comment = WeeklyReportComment::create([
            'weekly_report_id' => $weeklyReport->id,
            'user_id' => $user->id,
            'comment' => $validated['comment'],
        ]);

        // 若點評者為主管或管理員且週報非自己，將狀態更新為 reviewed
        if (($user->isManager() || $user->isAdmin()) && $weeklyReport->user_id !== $user->id) {
            $weeklyReport->update(['status' => 'reviewed']);

            // 通知週報作者
            $author = $weeklyReport->user;
            if ($author) {
                $author->notify(new EipSystemNotification(
                    title: '主管週報點評反饋',
                    message: "{$user->name} 已對您 {$weeklyReport->year} 年第 {$weeklyReport->week_number} 週週報提供指導評語",
                    type: 'weekly_report_comment',
                    actionUrl: route('weekly-reports.show', $weeklyReport->id),
                    senderName: $user->name,
                    extra: ['report_id' => $weeklyReport->id, 'comment_id' => $comment->id]
                ));
            }
        }

        AuditLog::log(
            'weekly_report_comment',
            "點評週報 [{$weeklyReport->year} W{$weeklyReport->week_number}]",
            $comment,
            ['report_id' => $weeklyReport->id, 'comment_id' => $comment->id]
        );

        return back()->with('success', '評語已順利送出！');
    }

    /**
     * 主管一鍵提醒未繳交週報同仁
     */
    public function remind(Request $request)
    {
        $user = $request->user();

        if (! $user->isManager() && ! $user->isAdmin()) {
            abort(403, '僅主管或管理員可發送催繳提醒');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'year' => ['required', 'integer'],
            'week_number' => ['required', 'integer'],
        ]);

        $targetUser = User::findOrFail($validated['user_id']);

        $targetUser->notify(new EipSystemNotification(
            title: '工作週報催繳提醒',
            message: "主管 {$user->name} 提醒您尚未提交 {$validated['year']} 年第 {$validated['week_number']} 週工作週報，請撥冗儘速填寫！",
            type: 'weekly_report_remind',
            actionUrl: route('weekly-reports.create', ['year' => $validated['year'], 'week' => $validated['week_number']]),
            senderName: $user->name,
            extra: ['year' => $validated['year'], 'week_number' => $validated['week_number']]
        ));

        AuditLog::log(
            'weekly_report_remind',
            "提醒 {$targetUser->name} 繳交第 {$validated['year']} 年第 {$validated['week_number']} 週週報",
            $targetUser,
            ['target_user_id' => $targetUser->id, 'year' => $validated['year'], 'week_number' => $validated['week_number']]
        );

        return back()->with('success', "已向 {$targetUser->name} 發送週報催繳提醒！");
    }
}
