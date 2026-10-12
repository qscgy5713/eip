<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * 建立新任務
     */
    public function store(Request $request, Project $project)
    {
        $user = $request->user();

        if (! $project->canAccess($user)) {
            abort(403, '您沒有此專案的任務建立權限');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:urgent,high,medium,low'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'status' => ['nullable', 'in:todo,in_progress,review,done'],
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'assignee_id' => $validated['assignee_id'] ?? null,
            'creator_id' => $user->id,
            'due_date' => $validated['due_date'] ?? null,
            'estimated_hours' => $validated['estimated_hours'] ?? null,
            'status' => $validated['status'] ?? 'todo',
            'completed_at' => ($validated['status'] ?? 'todo') === 'done' ? now() : null,
        ]);

        // 若被指派人尚非專案成員，自動加為成員
        if ($task->assignee_id && ! $project->members()->where('user_id', $task->assignee_id)->exists()) {
            $project->members()->attach($task->assignee_id, ['role' => 'member']);
        }

        // 發送通知給被指派人
        if ($task->assignee_id && $task->assignee_id !== $user->id) {
            $assignee = User::find($task->assignee_id);
            if ($assignee) {
                $assignee->notify(new EipSystemNotification(
                    title: '新專案任務指派',
                    message: "{$user->name} 在專案 [{$project->code}] 指派了任務給您：「{$task->title}」",
                    type: 'task',
                    actionUrl: route('projects.show', $project->id),
                    senderName: $user->name,
                    extra: ['project_id' => $project->id, 'task_id' => $task->id]
                ));
            }
        }

        AuditLog::log(
            'task_create',
            "在專案 [{$project->code}] 建立任務：{$task->title}",
            $task,
            ['project_id' => $project->id, 'task_id' => $task->id]
        );

        return back()->with('success', '任務建立成功！');
    }

    /**
     * 更新任務內容
     */
    public function update(Request $request, Task $task)
    {
        $user = $request->user();
        $project = $task->project;

        if (! $project->canAccess($user)) {
            abort(403, '您沒有編輯此任務的權限');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:urgent,high,medium,low'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'actual_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
        ]);

        $oldAssigneeId = $task->assignee_id;

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'assignee_id' => $validated['assignee_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'estimated_hours' => $validated['estimated_hours'] ?? null,
            'actual_hours' => $validated['actual_hours'] ?? null,
        ]);

        // 若被指派人尚非專案成員，自動加為成員
        if ($task->assignee_id && ! $project->members()->where('user_id', $task->assignee_id)->exists()) {
            $project->members()->attach($task->assignee_id, ['role' => 'member']);
        }

        // 若重新指派給不同人，發送通知
        if ($task->assignee_id && $task->assignee_id !== $oldAssigneeId && $task->assignee_id !== $user->id) {
            $assignee = User::find($task->assignee_id);
            if ($assignee) {
                $assignee->notify(new EipSystemNotification(
                    title: '專案任務重新指派',
                    message: "{$user->name} 在專案 [{$project->code}] 指派了任務給您：「{$task->title}」",
                    type: 'task',
                    actionUrl: route('projects.show', $project->id),
                    senderName: $user->name,
                    extra: ['project_id' => $project->id, 'task_id' => $task->id]
                ));
            }
        }

        AuditLog::log(
            'task_update',
            "更新專案 [{$project->code}] 任務：{$task->title}",
            $task,
            ['task_id' => $task->id]
        );

        return back()->with('success', '任務更新完成！');
    }

    /**
     * 快速更新任務狀態 (看板拖曳/切換)
     */
    public function updateStatus(Request $request, Task $task)
    {
        $user = $request->user();
        $project = $task->project;

        if (! $project->canAccess($user)) {
            abort(403, '您沒有變更此任務狀態的權限');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:todo,in_progress,review,done'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $oldStatus = $task->status;
        $newStatus = $validated['status'];

        $task->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'done' ? ($task->completed_at ?? now()) : null,
            'sort_order' => $validated['sort_order'] ?? $task->sort_order,
        ]);

        AuditLog::log(
            'task_status_update',
            "變更專案 [{$project->code}] 任務狀態：{$task->title} ({$oldStatus} -> {$newStatus})",
            $task,
            ['old_status' => $oldStatus, 'new_status' => $newStatus]
        );

        return back()->with('success', "任務狀態已變更為 {$newStatus}");
    }

    /**
     * 刪除任務
     */
    public function destroy(Task $task, Request $request)
    {
        $user = $request->user();
        $project = $task->project;

        // 僅限專案負責人、建立者或管理員
        if ($task->creator_id !== $user->id && ! $project->canManage($user)) {
            abort(403, '您沒有刪除此任務的權限');
        }

        $title = $task->title;

        AuditLog::log(
            'task_delete',
            "刪除專案 [{$project->code}] 任務：{$title}",
            $task,
            ['project_id' => $project->id, 'title' => $title]
        );

        $task->delete();

        return back()->with('success', '任務已成功刪除');
    }
}
