<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meal_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title'); // 揪團主題，例：迷客夏週三下午茶
            $table->string('store_name'); // 店家名稱
            $table->string('store_phone')->nullable(); // 店家電話
            $table->string('menu_url')->nullable(); // 菜單網址或連結
            $table->text('description')->nullable(); // 揪團說明或備註
            $table->dateTime('cutoff_time'); // 結單截止時間
            $table->decimal('subsidy_per_person', 8, 2)->default(0); // 公司每人補貼金額 (選填)
            $table->string('status')->default('open'); // open, closed, completed, cancelled
            $table->timestamps();

            $table->index(['status', 'cutoff_time']);
        });

        Schema::create('meal_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_order_id')->constrained('meal_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('item_name'); // 品項名稱，例：珍珠紅茶拿鐵
            $table->string('specification')->nullable(); // 規格/甜度冰塊，例：大杯 / 微糖微冰
            $table->decimal('unit_price', 8, 2); // 單價
            $table->unsignedInteger('quantity')->default(1); // 數量
            $table->decimal('subtotal', 8, 2); // 小計
            $table->string('notes')->nullable(); // 個人備註，例：自備環保杯
            $table->string('payment_status')->default('unpaid'); // unpaid, paid
            $table->timestamp('paid_at')->nullable(); // 付款時間
            $table->timestamps();

            $table->index(['meal_order_id', 'user_id']);
            $table->index(['meal_order_id', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_order_items');
        Schema::dropIfExists('meal_orders');
    }
};
