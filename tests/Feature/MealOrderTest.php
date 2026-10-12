<?php

namespace Tests\Feature;

use App\Models\MealOrder;
use App\Models\MealOrderItem;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $colleague;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'role' => 'employee',
        ]);
        $this->colleague = User::factory()->create([
            'role' => 'employee',
        ]);
    }

    public function test_user_can_view_meal_orders_index(): void
    {
        MealOrder::create([
            'user_id' => $this->user->id,
            'title' => '午後手搖揪團',
            'restaurant_name' => '50嵐',
            'cutoff_time' => now()->addHour(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)->get(route('meal-orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('MealOrders/Index')
            ->has('orders.data', 1)
            ->has('stats')
        );
    }

    public function test_user_can_create_a_meal_order(): void
    {
        $payload = [
            'title' => '梁社漢便當訂餐',
            'restaurant_name' => '梁社漢排骨',
            'restaurant_phone' => '02-12345678',
            'minimum_order_amount' => 500,
            'subsidy_per_person' => 50,
            'cutoff_time' => now()->addHours(2)->toDateTimeString(),
            'description' => '12:00 送達 5F 茶水間',
        ];

        $response = $this->actingAs($this->user)->post(route('meal-orders.store'), $payload);

        $order = MealOrder::latest()->first();
        $response->assertRedirect(route('meal-orders.show', $order->id));
        $this->assertDatabaseHas('meal_orders', [
            'creator_id' => $this->user->id,
            'title' => '梁社漢便當訂餐',
            'store_name' => '梁社漢排骨',
            'subsidy_per_person' => 50,
            'status' => 'open',
        ]);
    }

    public function test_colleague_can_add_meal_order_item(): void
    {
        $order = MealOrder::create([
            'user_id' => $this->user->id,
            'title' => '茶飲揪團',
            'restaurant_name' => '得正',
            'cutoff_time' => now()->addHour(),
            'status' => 'open',
        ]);

        $itemPayload = [
            'item_name' => '焙烏龍鮮奶',
            'unit_price' => 65,
            'quantity' => 2,
            'options' => '微糖微冰/大杯',
            'notes' => '不要吸管',
        ];

        $response = $this->actingAs($this->colleague)
            ->post(route('meal-orders.items.store', $order->id), $itemPayload);

        $response->assertRedirect();
        $this->assertDatabaseHas('meal_order_items', [
            'meal_order_id' => $order->id,
            'user_id' => $this->colleague->id,
            'item_name' => '焙烏龍鮮奶',
            'unit_price' => 65,
            'quantity' => 2,
            'subtotal' => 130, // 65 * 2
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_ordering_is_blocked_after_cutoff_or_when_closed(): void
    {
        // 建立已逾期訂單
        $expiredOrder = MealOrder::create([
            'user_id' => $this->user->id,
            'title' => '已逾期手搖',
            'restaurant_name' => '迷客夏',
            'cutoff_time' => now()->subMinute(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->colleague)
            ->post(route('meal-orders.items.store', $expiredOrder->id), [
                'item_name' => '珍珠紅茶拿鐵',
                'unit_price' => 70,
                'quantity' => 1,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('meal_order_items', 0);

        // 結單後亦禁止點餐
        $closedOrder = MealOrder::create([
            'user_id' => $this->user->id,
            'title' => '已結單便當',
            'restaurant_name' => '正忠排骨',
            'cutoff_time' => now()->addHour(),
            'status' => 'closed',
        ]);

        $response2 = $this->actingAs($this->colleague)
            ->post(route('meal-orders.items.store', $closedOrder->id), [
                'item_name' => '排骨飯',
                'unit_price' => 110,
                'quantity' => 1,
            ]);

        $response2->assertSessionHas('error');
        $this->assertDatabaseCount('meal_order_items', 0);
    }

    public function test_user_can_remove_own_order_item(): void
    {
        $order = MealOrder::create([
            'user_id' => $this->user->id,
            'title' => '咖啡揪團',
            'restaurant_name' => 'Cama',
            'cutoff_time' => now()->addHour(),
            'status' => 'open',
        ]);

        $item = MealOrderItem::create([
            'meal_order_id' => $order->id,
            'user_id' => $this->colleague->id,
            'item_name' => '拿鐵',
            'unit_price' => 85,
            'quantity' => 1,
            'subtotal' => 85,
        ]);

        $response = $this->actingAs($this->colleague)
            ->delete(route('meal-orders.items.destroy', [$order->id, $item->id]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('meal_order_items', ['id' => $item->id]);
    }

    public function test_creator_can_close_and_complete_order(): void
    {
        $order = MealOrder::create([
            'user_id' => $this->user->id,
            'title' => '午餐開團',
            'restaurant_name' => '便當屋',
            'cutoff_time' => now()->addHour(),
            'status' => 'open',
        ]);

        // 結單
        $response = $this->actingAs($this->user)->post(route('meal-orders.close', $order->id));
        $response->assertRedirect();
        $this->assertEquals('closed', $order->fresh()->status);

        // 送達完成
        $response2 = $this->actingAs($this->user)->post(route('meal-orders.complete', $order->id));
        $response2->assertRedirect();
        $this->assertEquals('completed', $order->fresh()->status);
    }

    public function test_creator_can_toggle_payment_status(): void
    {
        $order = MealOrder::create([
            'user_id' => $this->user->id,
            'title' => '點心開團',
            'restaurant_name' => '紅豆餅',
            'cutoff_time' => now()->addHour(),
            'status' => 'open',
        ]);

        $item = MealOrderItem::create([
            'meal_order_id' => $order->id,
            'user_id' => $this->colleague->id,
            'item_name' => '紅豆餅',
            'unit_price' => 25,
            'quantity' => 2,
            'payment_status' => 'unpaid',
        ]);

        // 切換為 paid
        $this->actingAs($this->user)->patch(route('meal-orders.items.toggle-payment', [$order->id, $item->id]));
        $this->assertEquals('paid', $item->fresh()->payment_status);

        // 切換回 unpaid
        $this->actingAs($this->user)->patch(route('meal-orders.items.toggle-payment', [$order->id, $item->id]));
        $this->assertEquals('unpaid', $item->fresh()->payment_status);
    }

    public function test_creator_can_send_payment_reminder_notifications(): void
    {
        $order = MealOrder::create([
            'user_id' => $this->user->id,
            'title' => '披薩大餐',
            'restaurant_name' => '達美樂',
            'cutoff_time' => now()->addHour(),
            'status' => 'open',
        ]);

        MealOrderItem::create([
            'meal_order_id' => $order->id,
            'user_id' => $this->colleague->id,
            'item_name' => '夏威夷小披薩',
            'unit_price' => 150,
            'quantity' => 1,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->user)->post(route('meal-orders.notify', $order->id));
        $response->assertRedirect();

        // 驗證同事收到站內通知
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->colleague->id,
            'notifiable_type' => User::class,
            'type' => \App\Notifications\EipSystemNotification::class,
        ]);
    }

    public function test_show_page_exposes_props_the_frontend_reads(): void
    {
        $order = MealOrder::create([
            'creator_id' => $this->user->id,
            'title' => '週三下午茶',
            'store_name' => '迷客夏',
            'cutoff_time' => now()->addHours(3),
            'subsidy_per_person' => 50,
            'status' => 'open',
        ]);
        \App\Models\MealOrderItem::create([
            'meal_order_id' => $order->id,
            'user_id' => $this->colleague->id,
            'item_name' => '玉露青茶',
            'specification' => '中杯 / 無糖去冰',
            'unit_price' => 40,
            'quantity' => 2,
            'subtotal' => 80,
        ]);

        $this->actingAs($this->user)->get(route('meal-orders.show', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MealOrders/Show')
                ->has('summaryItems', 1)
                ->where('summaryItems.0.total_quantity', 2)
                ->where('summaryItems.0.options', '中杯 / 無糖去冰')
                ->has('userSettlements', 1)
                ->where('userSettlements.0.user_name', $this->colleague->name)
                ->where('userSettlements.0.total_amount', 80)
                ->where('userSettlements.0.is_fully_paid', false)
                ->has('myItems', 0));
    }
}
