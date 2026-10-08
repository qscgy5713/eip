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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Webhook 識別名稱，如 "Slack 研發頻道", "HR Discord"
            $table->string('url', 500); // Webhook 呼叫端點
            $table->json('events'); // 訂閱事件陣列，如 ["announcement.published", "form.submitted", "form.approved"]
            $table->string('secret', 100)->nullable(); // 簽章驗證金鑰
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->string('last_status', 20)->nullable(); // success, failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
