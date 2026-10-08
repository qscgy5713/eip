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
        // 職務代理人設定表
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // 被代理人 (主管)
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete(); // 代理人
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 簽核歷程擴充：紀錄代簽資訊
        Schema::table('approval_records', function (Blueprint $table) {
            $table->foreignId('delegated_from_id')->nullable()->after('approver_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_records', function (Blueprint $table) {
            $table->dropForeign(['delegated_from_id']);
            $table->dropColumn('delegated_from_id');
        });

        Schema::dropIfExists('delegations');
    }
};
