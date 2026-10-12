<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Models\WeeklyReportComment;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WeeklyReportTest extends TestCase
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
            'name' => '軟體研發部',
            'code' => 'DEV',
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

        $this->department->update(['leader_id' => $this->manager->id]);

        $this->employee1 = User::factory()->create([
            'name' => '前端工程師小高',
            'email' => 'emp1@example.com',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->employee2 = User::factory()->create([
            'name' => '後端工程師小陳',
            'email' => 'emp2@example.com',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);
    }

    public function test_user_can_view_weekly_reports_index(): void
    {
        $response = $this->actingAs($this->employee1)->get(route('weekly-reports.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('WeeklyReports/Index')
            ->has('reports')
            ->has('currentWeekInfo')
        );
    }

    public function test_user_can_create_weekly_report_and_notifies_leader(): void
    {
        Notification::fake();

        $year = 2026;
        $week = 41;

        $response = $this->actingAs($this->employee1)->post(route('weekly-reports.store'), [
            'year' => $year,
            'week_number' => $week,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'summary' => '本週完成 EIP 週報協同回報系統模組',
            'achievements' => "1. 完成資料庫 schema 遷移\n2. 實作後端 Controller 與權限校驗\n3. 完成 Vue 3 前端介面",
            'plans_next_week' => '1. 進行壓力測試\n2. 收集跨部門同仁使用反饋',
            'challenges_or_support' => '需要 IT 部門協助評估排程排催設定',
            'is_draft' => false,
        ]);

        $response->assertRedirect();

        $report = WeeklyReport::where('user_id', $this->employee1->id)
            ->where('year', $year)
            ->where('week_number', $week)
            ->first();

        $this->assertNotNull($report);
        $this->assertEquals('submitted', $report->status);
        $this->assertNotNull($report->submitted_at);

        // 驗證發送通知給部門主管
        Notification::assertSentTo(
            $this->manager,
            EipSystemNotification::class,
            function ($notification) use ($report) {
                return str_contains($notification->title, '同仁工作週報提交通知')
                    && str_contains($notification->message, $this->employee1->name);
            }
        );

        // 驗證審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->employee1->id,
            'action' => 'weekly_report_submit',
            'auditable_type' => WeeklyReport::class,
            'auditable_id' => $report->id,
        ]);
    }

    public function test_user_can_save_draft_weekly_report_without_notifying_leader(): void
    {
        Notification::fake();

        $year = 2026;
        $week = 42;

        $response = $this->actingAs($this->employee1)->post(route('weekly-reports.store'), [
            'year' => $year,
            'week_number' => $week,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-25',
            'summary' => '初稿規劃',
            'achievements' => '規劃草案中...',
            'plans_next_week' => '待確認...',
            'is_draft' => true,
        ]);

        $response->assertRedirect();

        $report = WeeklyReport::where('user_id', $this->employee1->id)
            ->where('year', $year)
            ->where('week_number', $week)
            ->first();

        $this->assertNotNull($report);
        $this->assertEquals('draft', $report->status);
        $this->assertNull($report->submitted_at);

        // 草稿不發送通知給主管
        Notification::assertNothingSent();
    }

    public function test_cannot_create_duplicate_weekly_report_for_same_week(): void
    {
        WeeklyReport::create([
            'user_id' => $this->employee1->id,
            'department_id' => $this->department->id,
            'year' => 2026,
            'week_number' => 41,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'summary' => '已存在週報',
            'achievements' => '項目',
            'plans_next_week' => '計畫',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->employee1)->post(route('weekly-reports.store'), [
            'year' => 2026,
            'week_number' => 41,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'summary' => '嘗試重複建立',
            'achievements' => '項目',
            'plans_next_week' => '計畫',
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertEquals(1, WeeklyReport::where('user_id', $this->employee1->id)->where('week_number', 41)->count());
    }

    public function test_author_and_manager_can_view_report_and_other_department_cannot_view_idor_protection(): void
    {
        $otherDept = Department::create(['name' => '行銷企劃部', 'code' => 'MKT']);
        $otherUser = User::factory()->create([
            'role' => 'employee',
            'department_id' => $otherDept->id,
        ]);

        $report = WeeklyReport::create([
            'user_id' => $this->employee1->id,
            'department_id' => $this->department->id,
            'year' => 2026,
            'week_number' => 40,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-11',
            'summary' => '內部機密週報',
            'achievements' => '完成機密專案 A',
            'plans_next_week' => '下週任務',
            'status' => 'submitted',
        ]);

        // 作者本人可看
        $this->actingAs($this->employee1)
            ->get(route('weekly-reports.show', $report->id))
            ->assertStatus(200);

        // 同部門主管可看
        $this->actingAs($this->manager)
            ->get(route('weekly-reports.show', $report->id))
            ->assertStatus(200);

        // 管理員可看
        $this->actingAs($this->admin)
            ->get(route('weekly-reports.show', $report->id))
            ->assertStatus(200);

        // 其他部門外部同仁不可看 (403 防越權 IDOR 保護)
        $this->actingAs($otherUser)
            ->get(route('weekly-reports.show', $report->id))
            ->assertStatus(403);
    }

    public function test_author_can_update_weekly_report_before_reviewed(): void
    {
        $report = WeeklyReport::create([
            'user_id' => $this->employee1->id,
            'department_id' => $this->department->id,
            'year' => 2026,
            'week_number' => 39,
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-04',
            'summary' => '原版摘要',
            'achievements' => '完成一項任務',
            'plans_next_week' => '原有計畫',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->employee1)->put(route('weekly-reports.update', $report->id), [
            'summary' => '修訂後更新的摘要重點',
            'achievements' => '補充完成兩項任務與效能測試',
            'plans_next_week' => '全新計畫里程碑',
            'challenges_or_support' => '暫無',
            'is_draft' => false,
        ]);

        $response->assertRedirect();
        $report->refresh();
        $this->assertEquals('修訂後更新的摘要重點', $report->summary);
        $this->assertEquals('補充完成兩項任務與效能測試', $report->achievements);
    }

    public function test_author_cannot_update_weekly_report_after_reviewed_by_manager(): void
    {
        $report = WeeklyReport::create([
            'user_id' => $this->employee1->id,
            'department_id' => $this->department->id,
            'year' => 2026,
            'week_number' => 38,
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-27',
            'summary' => '已被點評之週報',
            'achievements' => '完成項目',
            'plans_next_week' => '計畫',
            'status' => 'reviewed', // 主管已批閱點評
        ]);

        $response = $this->actingAs($this->employee1)->put(route('weekly-reports.update', $report->id), [
            'summary' => '嘗試篡改已被批閱之內容',
            'achievements' => '完成項目',
            'plans_next_week' => '計畫',
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_can_comment_on_report_and_marks_as_reviewed_and_notifies_author(): void
    {
        Notification::fake();

        $report = WeeklyReport::create([
            'user_id' => $this->employee1->id,
            'department_id' => $this->department->id,
            'year' => 2026,
            'week_number' => 37,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-20',
            'summary' => '等待點評之週報',
            'achievements' => '完成項目',
            'plans_next_week' => '計畫',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->manager)->post(route('weekly-reports.comment', $report->id), [
            'comment' => '進度掌握扎實！下週壓測如有需要伺服器支援可隨時提出。',
        ]);

        $response->assertRedirect();
        $report->refresh();

        $this->assertEquals('reviewed', $report->status);
        $this->assertDatabaseHas('weekly_report_comments', [
            'weekly_report_id' => $report->id,
            'user_id' => $this->manager->id,
            'comment' => '進度掌握扎實！下週壓測如有需要伺服器支援可隨時提出。',
        ]);

        // 驗證發送通知給週報作者
        Notification::assertSentTo(
            $this->employee1,
            EipSystemNotification::class,
            function ($notification) {
                return str_contains($notification->title, '主管週報點評反饋')
                    && str_contains($notification->senderName, $this->manager->name);
            }
        );
    }

    public function test_manager_can_send_remind_notification_to_unsubmitted_user(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->manager)->post(route('weekly-reports.remind'), [
            'user_id' => $this->employee2->id,
            'year' => 2026,
            'week_number' => 41,
        ]);

        $response->assertRedirect();

        Notification::assertSentTo(
            $this->employee2,
            EipSystemNotification::class,
            function ($notification) {
                return str_contains($notification->title, '工作週報催繳提醒')
                    && str_contains($notification->message, '41 週');
            }
        );
    }

    public function test_dashboard_displays_weekly_report_status_and_week_number(): void
    {
        $now = Carbon::now();
        WeeklyReport::create([
            'user_id' => $this->employee1->id,
            'department_id' => $this->department->id,
            'year' => $now->year,
            'week_number' => $now->isoWeek(),
            'start_date' => $now->startOfWeek()->toDateString(),
            'end_date' => $now->endOfWeek()->toDateString(),
            'summary' => '本週最新衝刺成果',
            'achievements' => '任務完成',
            'plans_next_week' => '下週規劃',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->employee1)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('myWeeklyReport')
            ->where('currentWeekNumber', $now->isoWeek())
            ->where('stats.isWeeklyReportSubmitted', true)
        );
    }

    public function test_global_search_finds_weekly_reports_with_permission_filter(): void
    {
        $report = WeeklyReport::create([
            'user_id' => $this->employee1->id,
            'department_id' => $this->department->id,
            'year' => 2026,
            'week_number' => 36,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-13',
            'summary' => '專門研發 Redis 叢集高可用架構',
            'achievements' => '搭建 Sentinel 哨兵模式',
            'plans_next_week' => '壓力驗證',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->employee1)->get(route('global-search', ['q' => 'Redis']));
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'badge' => '週報',
            'type' => 'weekly_report',
        ]);
    }
}
