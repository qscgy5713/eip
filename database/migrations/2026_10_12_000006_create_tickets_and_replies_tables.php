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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // 工單流水號，例如 TK-20261012-0001
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // 提單同仁
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // 指派處理工程師/專員
            $table->string('category'); // it_hardware, it_software, it_network, facility, stationery, other
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('open'); // open, in_progress, pending_user, resolved, closed
            $table->string('title'); // 問題主旨
            $table->text('description'); // 詳細描述
            $table->string('location')->nullable(); // 故障地點/位置
            $table->json('attachments')->nullable(); // 照片截圖附件
            $table->timestamp('resolved_at')->nullable(); // 解決完修時間
            $table->timestamp('closed_at')->nullable(); // 結案時間
            $table->unsignedTinyInteger('satisfaction_rating')->nullable(); // 結案評分 (1~5 星)
            $table->text('satisfaction_feedback')->nullable(); // 滿意度反饋
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['user_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // 回覆者
            $table->text('message'); // 處理回覆說明
            $table->boolean('is_internal')->default(false); // 是否僅內部可見 (IT/Admin 專屬備註)
            $table->json('attachments')->nullable(); // 回覆附加檔案
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');
    }
};
