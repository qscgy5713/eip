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
        Schema::table('point_wallets', function (Blueprint $table) {
            // 感謝幣額度所屬月份 (YYYY-MM)，跨月時惰性重置為每月固定額度
            $table->string('quota_month', 7)->nullable()->after('recognition_quota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_wallets', function (Blueprint $table) {
            $table->dropColumn('quota_month');
        });
    }
};
