<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $employee1;
    protected User $employee2;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '研發部',
            'code' => 'RD',
        ]);

        $this->admin = User::factory()->create([
            'name' => '系統管理員',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->manager = User::factory()->create([
            'name' => '研發主管',
            'email' => 'manager@example.com',
            'role' => 'manager',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->employee1 = User::factory()->create([
            'name' => '同仁小張',
            'email' => 'emp1@example.com',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->employee2 = User::factory()->create([
            'name' => '同仁小李',
            'email' => 'emp2@example.com',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);
    }

    public function test_manager_can_create_project_with_members(): void
    {
        $response = $this->actingAs($this->manager)
            ->post(route('projects.store'), [
                'code' => 'PRJ-2026-001',
                'name' => '企業入口網 2.0 系統重構',
                'description' => '全新 Vue 3 + Tailwind 深色模式與專案看板協同',
                'department_id' => $this->department->id,
                'priority' => 'high',
                'status' => 'active',
                'start_date' => now()->toDateString(),
                'due_date' => now()->addMonths(1)->toDateString(),
                'member_ids' => [$this->employee1->id],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'code' => 'PRJ-2026-001',
            'name' => '企業入口網 2.0 系統重構',
            'owner_id' => $this->manager->id,
        ]);

        $project = Project::where('code', 'PRJ-2026-001')->first();
        $this->assertNotNull($project);
        $this->assertTrue($project->members->contains($this->manager->id));
        $this->assertTrue($project->members->contains($this->employee1->id));

        // 驗證審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project_create',
            'user_id' => $this->manager->id,
        ]);
    }

    public function test_general_employee_cannot_create_project(): void
    {
        $response = $this->actingAs($this->employee1)
            ->post(route('projects.store'), [
                'code' => 'PRJ-HACK',
                'name' => '未授權專案',
                'priority' => 'low',
                'status' => 'active',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('projects', ['code' => 'PRJ-HACK']);
    }

    public function test_project_access_permissions_and_idor_protection(): void
    {
        $project = Project::create([
            'code' => 'PRJ-SECRET',
            'name' => '研發機密核心專案',
            'owner_id' => $this->manager->id,
            'department_id' => $this->department->id,
            'status' => 'active',
            'priority' => 'urgent',
        ]);
        $project->members()->attach([$this->manager->id => ['role' => 'lead'], $this->employee1->id => ['role' => 'member']]);

        // 1. 成員 employee1 可以查看專案看板
        $this->actingAs($this->employee1)
            ->get(route('projects.show', $project->id))
            ->assertOk();

        // 2. 非成員 employee2 查看專案看板應被 403 阻擋 (IDOR 防護)
        $this->actingAs($this->employee2)
            ->get(route('projects.show', $project->id))
            ->assertStatus(403);

        // 3. 管理員 admin 具備全權查閱權限
        $this->actingAs($this->admin)
            ->get(route('projects.show', $project->id))
            ->assertOk();
    }

    public function test_create_task_and_dispatch_notification(): void
    {
        Notification::fake();

        $project = Project::create([
            'code' => 'PRJ-TASK-TEST',
            'name' => '任務測試專案',
            'owner_id' => $this->manager->id,
            'status' => 'active',
            'priority' => 'medium',
        ]);
        $project->members()->attach([$this->manager->id => ['role' => 'lead'], $this->employee1->id => ['role' => 'member']]);

        $response = $this->actingAs($this->manager)
            ->post(route('tasks.store', $project->id), [
                'title' => '實作甘特圖與看板元件',
                'description' => '完成 Vue 3 拖曳與狀態切換流轉',
                'priority' => 'high',
                'status' => 'todo',
                'assignee_id' => $this->employee1->id,
                'due_date' => now()->addDays(3)->toDateString(),
                'estimated_hours' => 8.0,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => '實作甘特圖與看板元件',
            'assignee_id' => $this->employee1->id,
            'status' => 'todo',
        ]);

        // 驗證向被指派人發送站內通知
        Notification::assertSentTo($this->employee1, EipSystemNotification::class, function ($notification) use ($project) {
            return str_contains($notification->title, '新專案任務指派')
                && str_contains($notification->message, $project->code);
        });

        // 驗證審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'task_create',
            'user_id' => $this->manager->id,
        ]);
    }

    public function test_task_status_lifecycle_and_completion_timestamp(): void
    {
        $project = Project::create([
            'code' => 'PRJ-CYCLE',
            'name' => '任務生命週期專案',
            'owner_id' => $this->manager->id,
            'status' => 'active',
            'priority' => 'medium',
        ]);
        $project->members()->attach($this->manager->id, ['role' => 'lead']);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => '撰寫自動化測試案例',
            'status' => 'todo',
            'priority' => 'medium',
            'creator_id' => $this->manager->id,
        ]);

        $this->assertNull($task->completed_at);

        // 1. 切換至 in_progress
        $this->actingAs($this->manager)
            ->patch(route('tasks.updateStatus', $task->id), ['status' => 'in_progress'])
            ->assertRedirect();
        $this->assertEquals('in_progress', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);

        // 2. 切換至 done -> 自動補齊 completed_at
        $this->actingAs($this->manager)
            ->patch(route('tasks.updateStatus', $task->id), ['status' => 'done'])
            ->assertRedirect();
        $this->assertEquals('done', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);

        // 3. 若退回 review -> 清空 completed_at
        $this->actingAs($this->manager)
            ->patch(route('tasks.updateStatus', $task->id), ['status' => 'review'])
            ->assertRedirect();
        $this->assertEquals('review', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_update_and_delete_project_and_task(): void
    {
        $project = Project::create([
            'code' => 'PRJ-EDIT',
            'name' => '準備更名之專案',
            'owner_id' => $this->manager->id,
            'status' => 'active',
            'priority' => 'medium',
        ]);
        $project->members()->attach($this->manager->id, ['role' => 'lead']);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => '臨時性排程任務',
            'status' => 'todo',
            'priority' => 'low',
            'creator_id' => $this->manager->id,
        ]);

        // 1. 編輯專案
        $this->actingAs($this->manager)
            ->put(route('projects.update', $project->id), [
                'name' => '已更名之正式專案',
                'priority' => 'urgent',
                'status' => 'active',
            ])
            ->assertRedirect();
        $this->assertEquals('已更名之正式專案', $project->fresh()->name);
        $this->assertEquals('urgent', $project->fresh()->priority);

        // 2. 刪除任務
        $this->actingAs($this->manager)
            ->delete(route('tasks.destroy', $task->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);

        // 3. 刪除專案
        $this->actingAs($this->manager)
            ->delete(route('projects.destroy', $project->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_global_search_includes_projects(): void
    {
        $project = Project::create([
            'code' => 'PRJ-SEARCH-99',
            'name' => '企業全球佈局數位轉型方案',
            'description' => '建置全方位 EIP 與自動化辦公流程',
            'owner_id' => $this->manager->id,
            'status' => 'active',
            'priority' => 'high',
        ]);
        $project->members()->attach($this->manager->id, ['role' => 'lead']);

        $response = $this->actingAs($this->manager)
            ->getJson(route('global-search', ['q' => '數位轉型']));

        $response->assertOk()
            ->assertJsonPath('results.projects.title', '專案協同看板')
            ->assertJsonFragment([
                'id' => $project->id,
                'title' => "[PRJ-SEARCH-99] 企業全球佈局數位轉型方案",
            ]);
    }

    public function test_dashboard_displays_my_pending_tasks(): void
    {
        $project = Project::create([
            'code' => 'PRJ-DASH-1',
            'name' => '儀表板整合專案',
            'owner_id' => $this->manager->id,
            'status' => 'active',
            'priority' => 'medium',
        ]);
        $project->members()->attach([$this->manager->id => ['role' => 'lead'], $this->employee1->id => ['role' => 'member']]);

        Task::create([
            'project_id' => $project->id,
            'title' => '待辦工作台任務A',
            'status' => 'in_progress',
            'priority' => 'urgent',
            'assignee_id' => $this->employee1->id,
            'creator_id' => $this->manager->id,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($this->employee1)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertInertia(function ($page) {
                $page->component('Dashboard')
                    ->has('myTasks', 1)
                    ->where('stats.myPendingTasksCount', 1);
            });
    }

    public function test_assigning_task_automatically_adds_user_to_project_members(): void
    {
        $project = Project::create([
            'code' => 'PRJ-AUTO-MEMBER',
            'name' => '自動加入成員專案',
            'owner_id' => $this->manager->id,
            'status' => 'active',
            'priority' => 'medium',
        ]);
        $project->members()->attach($this->manager->id, ['role' => 'lead']);

        $this->assertFalse($project->members()->where('user_id', $this->employee2->id)->exists());

        // 主管指派任務給 employee2 (原先不是專案成員)
        $response = $this->actingAs($this->manager)
            ->post(route('tasks.store', $project->id), [
                'title' => '指派給外部成員之任務',
                'priority' => 'high',
                'assignee_id' => $this->employee2->id,
            ]);

        $response->assertRedirect();
        // 驗證 employee2 已自動加入 project_members
        $this->assertTrue($project->fresh()->members->contains($this->employee2->id));

        // 驗證 employee2 現在具備專案調閱權，存取專案看板不會被 403 阻擋
        $showResponse = $this->actingAs($this->employee2)
            ->get(route('projects.show', $project->id));
        $showResponse->assertOk();
    }

    public function test_task_due_today_is_not_overdue(): void
    {
        $project = Project::create([
            'code' => 'PRJ-OVERDUE-TEST',
            'name' => '逾期判定測試專案',
            'owner_id' => $this->manager->id,
            'status' => 'active',
            'priority' => 'medium',
        ]);

        $taskToday = Task::create([
            'project_id' => $project->id,
            'title' => '今天截止任務',
            'status' => 'in_progress',
            'priority' => 'medium',
            'creator_id' => $this->manager->id,
            'due_date' => now()->toDateString(),
        ]);

        $taskYesterday = Task::create([
            'project_id' => $project->id,
            'title' => '昨天截止任務',
            'status' => 'in_progress',
            'priority' => 'medium',
            'creator_id' => $this->manager->id,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        // 今天截止不應算逾期
        $this->assertFalse($taskToday->isOverdue());
        // 昨天截止應算逾期
        $this->assertTrue($taskYesterday->isOverdue());
    }
}
