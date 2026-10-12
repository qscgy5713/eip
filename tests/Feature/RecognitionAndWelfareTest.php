<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PointWallet;
use App\Models\RecognitionPost;
use App\Models\User;
use App\Models\WelfareRedemption;
use App\Models\WelfareReward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecognitionAndWelfareTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $peer;
    protected User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();

        $dept = Department::create(['name' => '資訊研發部', 'code' => 'IT']);

        $this->user = User::factory()->create(['name' => '王小明', 'department_id' => $dept->id, 'role' => 'employee']);
        $this->peer = User::factory()->create(['name' => '陳大偉', 'department_id' => $dept->id, 'role' => 'employee']);
        $this->hrUser = User::factory()->create(['name' => '林人資', 'department_id' => $dept->id, 'role' => 'hr']);
    }

    private function giveKudos(User $from, User $to, array $overrides = [])
    {
        return $this->actingAs($from)->post(route('recognition.store'), array_merge([
            'recipient_id' => $to->id,
            'points' => 20,
            'core_value' => 'teamwork',
            'message' => '謝謝你幫忙處理線上事故！',
        ], $overrides));
    }

    public function test_pages_render_for_employee(): void
    {
        $this->actingAs($this->user)->get(route('recognition.index'))->assertOk();
        $this->actingAs($this->user)->get(route('welfare.index'))->assertOk();
    }

    public function test_give_kudos_moves_quota_and_points(): void
    {
        $this->giveKudos($this->user, $this->peer)->assertRedirect();

        $this->assertDatabaseHas('recognition_posts', [
            'sender_id' => $this->user->id,
            'recipient_id' => $this->peer->id,
            'points' => 20,
        ]);
        $this->assertSame(80, PointWallet::where('user_id', $this->user->id)->value('recognition_quota'));
        $this->assertSame(20, PointWallet::where('user_id', $this->peer->id)->value('reward_points'));
        $this->assertCount(1, $this->peer->notifications);
    }

    public function test_cannot_give_to_self_or_inactive_or_over_quota(): void
    {
        $this->giveKudos($this->user, $this->user)->assertSessionHasErrors('recipient_id');

        $inactive = User::factory()->create(['status' => 'suspended']);
        $this->giveKudos($this->user, $inactive)->assertSessionHasErrors('recipient_id');

        $this->giveKudos($this->user, $this->peer, ['points' => 51])->assertSessionHasErrors('points');

        PointWallet::getOrCreateForUser($this->user)->update(['recognition_quota' => 5]);
        $this->giveKudos($this->user, $this->peer, ['points' => 10])->assertSessionHasErrors('points');

        $this->assertSame(0, RecognitionPost::count());
        $this->assertSame(5, PointWallet::where('user_id', $this->user->id)->value('recognition_quota'));
    }

    public function test_quota_resets_on_new_month(): void
    {
        $wallet = PointWallet::getOrCreateForUser($this->user);
        $wallet->update(['recognition_quota' => 3, 'quota_month' => now()->subMonth()->format('Y-m')]);

        $this->giveKudos($this->user, $this->peer, ['points' => 10])->assertRedirect();

        $this->assertSame(90, $wallet->fresh()->recognition_quota);
    }

    public function test_anonymous_sender_is_hidden_from_wall_but_visible_to_sender(): void
    {
        $this->giveKudos($this->user, $this->peer, ['is_anonymous' => true]);

        $this->actingAs($this->hrUser)->get(route('recognition.index'))
            ->assertInertia(fn ($page) => $page
                ->where('posts.data.0.is_anonymous', true)
                ->where('posts.data.0.sender', null)
                ->missing('posts.data.0.sender_id')
                ->where('posts.data.0.can_delete', false));

        $this->actingAs($this->user)->get(route('recognition.index'))
            ->assertInertia(fn ($page) => $page
                ->where('posts.data.0.sender.name', '王小明')
                ->where('posts.data.0.can_delete', true));
    }

    public function test_like_toggles_and_counts(): void
    {
        $this->giveKudos($this->user, $this->peer);
        $post = RecognitionPost::first();

        $this->actingAs($this->hrUser)->post(route('recognition.like', $post));
        $this->assertSame(1, $post->fresh()->likes_count);

        $this->actingAs($this->hrUser)->post(route('recognition.like', $post));
        $this->assertSame(0, $post->fresh()->likes_count);
    }

    public function test_only_sender_or_admin_can_delete_post(): void
    {
        $this->giveKudos($this->user, $this->peer);
        $post = RecognitionPost::first();

        $this->actingAs($this->peer)->delete(route('recognition.destroy', $post))->assertForbidden();
        $this->actingAs($this->user)->delete(route('recognition.destroy', $post))->assertRedirect();
        $this->assertDatabaseMissing('recognition_posts', ['id' => $post->id]);
    }

    public function test_hr_manages_rewards_and_employee_cannot(): void
    {
        $payload = ['name' => '星巴克百元飲品券', 'points_cost' => 50, 'stock' => 2, 'category' => 'beverage'];

        $this->actingAs($this->user)->post(route('welfare.rewards.store'), $payload)->assertForbidden();
        $this->actingAs($this->hrUser)->post(route('welfare.rewards.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('welfare_rewards', ['name' => '星巴克百元飲品券', 'is_active' => true]);

        $reward = WelfareReward::first();
        $this->actingAs($this->user)->patch(route('welfare.rewards.toggle', $reward))->assertForbidden();
        $this->actingAs($this->hrUser)->patch(route('welfare.rewards.toggle', $reward));
        $this->assertFalse($reward->fresh()->is_active);
    }

    public function test_redeem_deducts_points_and_stock_then_fulfill(): void
    {
        $reward = WelfareReward::create(['name' => '電影票', 'points_cost' => 30, 'stock' => 1, 'category' => 'leisure']);
        PointWallet::getOrCreateForUser($this->user)->update(['reward_points' => 50]);

        $this->actingAs($this->user)->post(route('welfare.redeem', $reward))->assertRedirect();

        $this->assertSame(20, PointWallet::where('user_id', $this->user->id)->value('reward_points'));
        $this->assertSame(0, $reward->fresh()->stock);
        $redemption = WelfareRedemption::first();
        $this->assertSame('pending', $redemption->status);
        $this->assertMatchesRegularExpression('/^WR-\d{8}-0001$/', $redemption->redemption_code);

        // 庫存為 0 無法再兌換
        PointWallet::where('user_id', $this->user->id)->update(['reward_points' => 100]);
        $this->actingAs($this->user)->post(route('welfare.redeem', $reward))->assertSessionHasErrors('error');
        $this->assertSame(1, WelfareRedemption::count());

        $this->actingAs($this->user)->post(route('welfare.fulfill', $redemption))->assertForbidden();
        $this->actingAs($this->hrUser)->post(route('welfare.fulfill', $redemption))->assertRedirect();
        $this->assertSame('fulfilled', $redemption->fresh()->status);
        $this->assertCount(1, $this->user->notifications);
    }

    public function test_hr_can_open_welfare_page_while_redemptions_are_pending(): void
    {
        $reward = WelfareReward::create(['name' => '咖啡券', 'points_cost' => 10, 'stock' => 3, 'category' => 'beverage']);
        PointWallet::getOrCreateForUser($this->user)->update(['reward_points' => 50]);
        $this->actingAs($this->user)->post(route('welfare.redeem', $reward))->assertRedirect();

        $this->actingAs($this->hrUser)->get(route('welfare.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welfare/Index')
                ->has('manageQueue', 1)
                ->where('manageQueue.0.reward.category', 'beverage'));
    }

    public function test_redeem_rejects_insufficient_points_and_inactive_reward(): void
    {
        $reward = WelfareReward::create(['name' => '禮品卡', 'points_cost' => 100, 'stock' => -1, 'category' => 'gift_card']);
        $this->actingAs($this->user)->post(route('welfare.redeem', $reward))->assertSessionHasErrors('error');

        PointWallet::getOrCreateForUser($this->user)->update(['reward_points' => 500]);
        $reward->update(['is_active' => false]);
        $this->actingAs($this->user)->post(route('welfare.redeem', $reward))->assertSessionHasErrors('error');

        $this->assertSame(500, PointWallet::where('user_id', $this->user->id)->value('reward_points'));
        $this->assertSame(0, WelfareRedemption::count());
    }

    public function test_cancel_refunds_points_and_stock_only_for_pending(): void
    {
        $reward = WelfareReward::create(['name' => '咖啡券', 'points_cost' => 40, 'stock' => 3, 'category' => 'beverage']);
        PointWallet::getOrCreateForUser($this->user)->update(['reward_points' => 40]);

        $this->actingAs($this->user)->post(route('welfare.redeem', $reward));
        $redemption = WelfareRedemption::first();

        // 他人不可取消
        $this->actingAs($this->peer)->post(route('welfare.cancel', $redemption))->assertForbidden();

        $this->actingAs($this->user)->post(route('welfare.cancel', $redemption))->assertRedirect();
        $this->assertSame('cancelled', $redemption->fresh()->status);
        $this->assertSame(40, PointWallet::where('user_id', $this->user->id)->value('reward_points'));
        $this->assertSame(3, $reward->fresh()->stock);

        // 已取消不可重複退點
        $this->actingAs($this->user)->post(route('welfare.cancel', $redemption))->assertSessionHasErrors('error');
        $this->assertSame(40, PointWallet::where('user_id', $this->user->id)->value('reward_points'));
    }

    public function test_employee_does_not_see_inactive_rewards_or_manage_queue(): void
    {
        WelfareReward::create(['name' => '上架中', 'points_cost' => 10, 'stock' => -1, 'category' => 'gift_card', 'is_active' => true]);
        WelfareReward::create(['name' => '已下架', 'points_cost' => 10, 'stock' => -1, 'category' => 'gift_card', 'is_active' => false]);

        $this->actingAs($this->user)->get(route('welfare.index'))
            ->assertInertia(fn ($page) => $page->has('rewards', 1)->where('canManage', false)->where('manageQueue', []));

        $this->actingAs($this->hrUser)->get(route('welfare.index'))
            ->assertInertia(fn ($page) => $page->has('rewards', 2)->where('canManage', true));
    }

    public function test_global_search_finds_only_active_rewards(): void
    {
        WelfareReward::create(['name' => '星巴克飲品券', 'points_cost' => 50, 'stock' => -1, 'category' => 'beverage', 'is_active' => true]);
        WelfareReward::create(['name' => '星巴克隱藏券', 'points_cost' => 50, 'stock' => -1, 'category' => 'beverage', 'is_active' => false]);

        $this->actingAs($this->user)->getJson(route('global-search', ['q' => '星巴克']))
            ->assertOk()
            ->assertJsonCount(1, 'results.welfare_rewards.items')
            ->assertJsonPath('results.welfare_rewards.items.0.title', '【福利】星巴克飲品券');
    }

    public function test_dashboard_shows_wallet_and_latest_kudos(): void
    {
        $this->giveKudos($this->user, $this->peer, ['is_anonymous' => true]);

        $this->actingAs($this->peer)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.myRewardPoints', 20)
                ->where('latestKudos.0.sender_name', '匿名同仁'));
    }
}
