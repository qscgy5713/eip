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
        // 1. 同仁福利點數與感謝幣錢包
        Schema::create('point_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('recognition_quota')->default(100); // 本月可送出的感謝幣額度
            $table->unsignedInteger('reward_points')->default(0); // 獲得的福利點數 (可用於兌換禮券)
            $table->timestamps();
        });

        // 2. 企業文化牆與互助感謝發文
        Schema::create('recognition_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('points')->default(10); // 贈送感謝幣數量 (1~50)
            $table->string('core_value')->default('teamwork'); // teamwork(團隊協同), excellence(追求卓越), helpfulness(熱心助人), innovation(創新突破), customer_first(客戶導向)
            $table->text('message'); // 感謝回饋附言
            $table->boolean('is_anonymous')->default(false); // 是否匿名送出
            $table->unsignedInteger('likes_count')->default(0); // 點讚數
            $table->timestamps();

            $table->index(['created_at', 'core_value']);
        });

        // 3. 文化牆貼文點讚明細
        Schema::create('recognition_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recognition_post_id')->constrained('recognition_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['recognition_post_id', 'user_id']);
        });

        // 4. 企業福利點數商城商品清單
        Schema::create('welfare_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 商品名稱 (例如：星巴克百元飲品券、電影票兩張、全家禮物卡 200 元、舒壓半日休假券)
            $table->text('description')->nullable();
            $table->unsignedInteger('points_cost'); // 兌換所需福利點數
            $table->integer('stock')->default(-1); // 庫存 (-1 代表無限量供應)
            $table->string('category')->default('gift_card'); // gift_card(便利禮券), beverage(咖啡飲品), leisure(休閒育樂), leave_credit(榮譽休假)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. 福利商品兌換紀錄
        Schema::create('welfare_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('redemption_code')->unique(); // 兌換序號 WR-YYYYMMDD-XXXX
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('welfare_reward_id')->constrained('welfare_rewards')->cascadeOnDelete();
            $table->unsignedInteger('points_spent');
            $table->string('status')->default('pending'); // pending(待人資發放/核銷), fulfilled(已發放領取), cancelled(已取消退點)
            $table->dateTime('fulfilled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('welfare_redemptions');
        Schema::dropIfExists('welfare_rewards');
        Schema::dropIfExists('recognition_likes');
        Schema::dropIfExists('recognition_posts');
        Schema::dropIfExists('point_wallets');
    }
};
