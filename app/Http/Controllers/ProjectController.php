<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * 專案清單與篩選
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'active');
        $search = $request->query('search');

        $query = Project::with(['owner', 'department', 'members'])
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'done');
                },
            ]);

        // 權限隔離：一般同仁與主管非管理員情況下，僅能檢視自己是負責人、成員或有指派任務之專案
        if ($user->role !== 'admin') {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', function ($mq) use ($user) {
                      $mq->where('user_id', $user->id);
                  })
                  ->orWhereHas('tasks', function ($tq) use ($user) {
                      $tq->where('assignee_id', $user->id);
                  });
            });
        }

        // 狀態篩選
        if ($status === 'active') {
            $query->where('status', 'active');
        } elseif ($status === 'completed') {
            $query->where('status', 'completed');
        } elseif ($status === 'on_hold') {
            $query->where('status', 'on_hold');
        } elseif ($status === 'my') {
            $query->where('owner_id', $user->id);
        }

        // 關鍵字搜尋
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $projects = $query->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $projects->through(function ($p) {
            $total = $p->total_tasks_count ?? 0;
            $completed = $p->completed_tasks_count ?? 0;
            $p->progress_percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
            return $p;
        });

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $users = User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'employee_no', 'job_title', 'department_id']);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'departments' => $departments,
            'users' => $users,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'canManage' => $user->isManager() || $user->isAdmin(),
        ]);
    }

    /**
     * 建立新專案
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // 限主管或管理員
        if (! $user->isManager() && ! $user->isAdmin()) {
            abort(403, '僅主管或系統管理員可發起專案');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:projects,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'priority' => ['required', 'in:urgent,high,medium,low'],
            'status' => ['required', 'in:active,on_hold,completed,archived'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);

        $project = DB::transaction(function () use ($validated, $user) {
            $project = Project::create([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'owner_id' => $user->id,
                'department_id' => $validated['department_id'] ?? $user->department_id,
                'priority' => $validated['priority'],
                'status' => $validated['status'],
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
            ]);

            $memberIds = collect($validated['member_ids'] ?? [])
                ->push($user->id)
                ->unique()
                ->values();

            $syncData = [];
            foreach ($memberIds as $mId) {
                $syncData[$mId] = ['role' => ($mId === $user->id ? 'lead' : 'member')];
            }

            $project->members()->sync($syncData);

            return $project;
        });

        AuditLog::log(
            'project_create',
            "建立新專案 [{$project->code}] {$project->name}",
            $project,
            ['project_id' => $project->id, 'code' => $project->code]
        );

        return redirect()->route('projects.show', $project->id)
            ->with('success', '專案建立成功！');
    }

    /**
     * 專案看板與詳細資訊
     */
    public function show(Project $project, Request $request)
    {
        $user = $request->user();

        if (! $project->canAccess($user)) {
            abort(403, '您沒有權限檢視此專案');
        }

        $project->load([
            'owner:id,name,email,job_title',
            'department:id,name',
            'members:id,name,email,job_title',
            'tasks' => function ($q) {
                $q->with(['assignee:id,name,email', 'creator:id,name'])
                  ->orderBy('sort_order')
                  ->orderByDesc('id');
            },
        ]);

        // 計算各狀態任務
        $tasksByStatus = [
            'todo' => $project->tasks->where('status', 'todo')->values(),
            'in_progress' => $project->tasks->where('status', 'in_progress')->values(),
            'review' => $project->tasks->where('status', 'review')->values(),
            'done' => $project->tasks->where('status', 'done')->values(),
        ];

        $total = $project->tasks->count();
        $completed = $project->tasks->where('status', 'done')->count();
        $progressPercent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $availableUsers = User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'job_title']);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'tasksByStatus' => $tasksByStatus,
            'progressPercent' => $progressPercent,
            'availableUsers' => $availableUsers,
            'canManage' => $project->canManage($user),
        ]);
    }

    /**
     * 更新專案資訊
     */
    public function update(Request $request, Project $project)
    {
        $user = $request->user();

        if (! $project->canManage($user)) {
            abort(403, '僅專案負責人或系統管理員可編輯專案');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'priority' => ['required', 'in:urgent,high,medium,low'],
            'status' => ['required', 'in:active,on_hold,completed,archived'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);

        DB::transaction(function () use ($project, $validated) {
            $project->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'priority' => $validated['priority'],
                'status' => $validated['status'],
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
            ]);

            if (isset($validated['member_ids'])) {
                $memberIds = collect($validated['member_ids'])
                    ->push($project->owner_id)
                    ->unique()
                    ->values();

                $syncData = [];
                foreach ($memberIds as $mId) {
                    $syncData[$mId] = ['role' => ($mId === $project->owner_id ? 'lead' : 'member')];
                }
                $project->members()->sync($syncData);
            }
        });

        AuditLog::log(
            'project_update',
            "更新專案 [{$project->code}] {$project->name}",
            $project,
            ['project_id' => $project->id]
        );

        return back()->with('success', '專案資料已更新！');
    }

    /**
     * 刪除專案
     */
    public function destroy(Project $project, Request $request)
    {
        $user = $request->user();

        if (! $project->canManage($user)) {
            abort(403, '僅專案負責人或系統管理員可刪除專案');
        }

        $code = $project->code;
        $name = $project->name;

        AuditLog::log(
            'project_delete',
            "刪除專案 [{$code}] {$name}",
            $project,
            ['code' => $code, 'name' => $name]
        );

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', "專案 [{$code}] 已順利刪除");
    }
}
