<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\PollVoter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollTest extends TestCase
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
            'name' => '技術部',
            'code' => 'TECH',
        ]);

        $this->admin = User::factory()->create([
            'name' => '系統管理員',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->manager = User::factory()->create([
            'name' => '技術主管',
            'email' => 'manager@example.com',
            'role' => 'manager',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->employee1 = User::factory()->create([
            'name' => '工程師小張',
            'email' => 'emp1@example.com',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->employee2 = User::factory()->create([
            'name' => '工程師小李',
            'email' => 'emp2@example.com',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);
    }

    public function test_user_can_view_polls_index(): void
    {
        $poll = Poll::create([
            'creator_id' => $this->manager->id,
            'title' => '2026 福委會聚餐地點票選',
            'description' => '請大家挑選喜歡的餐廳',
            'is_multiple_choice' => false,
            'is_anonymous' => true,
            'status' => 'active',
        ]);

        $poll->options()->create(['option_text' => '日式料理', 'sort_order' => 0]);
        $poll->options()->create(['option_text' => '泰式海鮮', 'sort_order' => 1]);

        $response = $this->actingAs($this->employee1)
            ->get(route('polls.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Polls/Index')
            ->has('polls.data', 1)
        );
    }

    public function test_manager_can_create_poll_with_options(): void
    {
        $response = $this->actingAs($this->manager)
            ->post(route('polls.store'), [
                'title' => '部門年度聚餐意向調查',
                'description' => '每人一票，挑選下週五餐廳',
                'is_multiple_choice' => false,
                'is_anonymous' => false,
                'ends_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'options' => [
                    '牛排餐酒館',
                    '義式手作薄餅',
                    '壽喜燒吃到飽',
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('polls', [
            'title' => '部門年度聚餐意向調查',
            'creator_id' => $this->manager->id,
            'is_anonymous' => false,
        ]);

        $poll = Poll::where('title', '部門年度聚餐意向調查')->first();
        $this->assertCount(3, $poll->options);

        // 驗證審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->manager->id,
            'auditable_type' => Poll::class,
            'auditable_id' => $poll->id,
            'action' => 'create',
        ]);
    }

    public function test_regular_employee_cannot_create_poll(): void
    {
        $response = $this->actingAs($this->employee1)
            ->post(route('polls.store'), [
                'title' => '一般同仁試圖發起投票',
                'options' => ['選項 A', '選項 B'],
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('polls', [
            'title' => '一般同仁試圖發起投票',
        ]);
    }

    public function test_employee_can_vote_in_single_choice_poll(): void
    {
        $poll = Poll::create([
            'creator_id' => $this->manager->id,
            'title' => '員工旅遊地點票選',
            'is_multiple_choice' => false,
            'is_anonymous' => false,
            'status' => 'active',
        ]);

        $opt1 = $poll->options()->create(['option_text' => '花蓮太魯閣', 'sort_order' => 0]);
        $opt2 = $poll->options()->create(['option_text' => '澎湖跳島行', 'sort_order' => 1]);

        $response = $this->actingAs($this->employee1)
            ->post(route('polls.vote', $poll), [
                'option_ids' => [$opt1->id],
            ]);

        $response->assertRedirect();
        
        // 驗證投票者紀錄與得票明細
        $this->assertDatabaseHas('poll_voters', [
            'poll_id' => $poll->id,
            'user_id' => $this->employee1->id,
        ]);

        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $poll->id,
            'poll_option_id' => $opt1->id,
            'user_id' => $this->employee1->id,
        ]);

        $this->assertTrue($poll->hasVoted($this->employee1));
        $this->assertFalse($poll->hasVoted($this->employee2));
    }

    public function test_anonymous_poll_stores_null_user_id_for_vote_privacy(): void
    {
        $poll = Poll::create([
            'creator_id' => $this->manager->id,
            'title' => '不記名政策滿意度調查',
            'is_multiple_choice' => false,
            'is_anonymous' => true,
            'status' => 'active',
        ]);

        $opt1 = $poll->options()->create(['option_text' => '非常滿意', 'sort_order' => 0]);
        $opt2 = $poll->options()->create(['option_text' => '尚有改進空間', 'sort_order' => 1]);

        $response = $this->actingAs($this->employee1)
            ->post(route('polls.vote', $poll), [
                'option_ids' => [$opt1->id],
            ]);

        $response->assertRedirect();

        // 匿名投票中，poll_voters 仍記錄以防重複投票
        $this->assertDatabaseHas('poll_voters', [
            'poll_id' => $poll->id,
            'user_id' => $this->employee1->id,
        ]);

        // 但 poll_votes 的 user_id 必為 NULL，徹底無法追溯選項是誰投的！
        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $poll->id,
            'poll_option_id' => $opt1->id,
            'user_id' => null,
        ]);
    }

    public function test_user_cannot_vote_twice(): void
    {
        $poll = Poll::create([
            'creator_id' => $this->manager->id,
            'title' => '每人限投一票測試',
            'is_multiple_choice' => false,
            'is_anonymous' => false,
            'status' => 'active',
        ]);

        $opt1 = $poll->options()->create(['option_text' => '選項 A', 'sort_order' => 0]);
        $opt2 = $poll->options()->create(['option_text' => '選項 B', 'sort_order' => 1]);

        // 第一次投票成功
        $this->actingAs($this->employee1)
            ->post(route('polls.vote', $poll), [
                'option_ids' => [$opt1->id],
            ]);

        // 第二次重複投票被阻擋
        $response = $this->actingAs($this->employee1)
            ->post(route('polls.vote', $poll), [
                'option_ids' => [$opt2->id],
            ]);

        $response->assertSessionHasErrors(['vote']);
        $this->assertEquals(1, $poll->voters()->count());
    }

    public function test_user_cannot_vote_in_closed_poll(): void
    {
        $poll = Poll::create([
            'creator_id' => $this->manager->id,
            'title' => '已結束投票測試',
            'is_multiple_choice' => false,
            'is_anonymous' => false,
            'status' => 'closed',
        ]);

        $opt1 = $poll->options()->create(['option_text' => '選項 A', 'sort_order' => 0]);
        $opt2 = $poll->options()->create(['option_text' => '選項 B', 'sort_order' => 1]);

        $response = $this->actingAs($this->employee1)
            ->post(route('polls.vote', $poll), [
                'option_ids' => [$opt1->id],
            ]);

        $response->assertSessionHasErrors(['vote']);
        $this->assertEquals(0, $poll->voters()->count());
    }

    public function test_creator_can_manually_close_poll(): void
    {
        $poll = Poll::create([
            'creator_id' => $this->manager->id,
            'title' => '測試手動提早關閉投票',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->manager)
            ->post(route('polls.close', $poll));

        $response->assertRedirect();
        $this->assertEquals('closed', $poll->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->manager->id,
            'auditable_type' => Poll::class,
            'auditable_id' => $poll->id,
            'action' => 'close_poll',
        ]);
    }

    public function test_creator_or_admin_can_delete_poll(): void
    {
        $poll = Poll::create([
            'creator_id' => $this->manager->id,
            'title' => '測試刪除投票活動',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('polls.destroy', $poll));

        $response->assertRedirect(route('polls.index'));
        $this->assertDatabaseMissing('polls', ['id' => $poll->id]);
    }

    public function test_global_search_can_find_poll(): void
    {
        $poll = Poll::create([
            'creator_id' => $this->manager->id,
            'title' => '2026年終福委尾牙提案',
            'description' => '包含飯店與外燴選項',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->employee1)
            ->getJson(route('global-search', ['q' => '尾牙']));

        $response->assertStatus(200);
        $response->assertJsonPath('results.polls.items.0.title', '2026年終福委尾牙提案');
    }
}
