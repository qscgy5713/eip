<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // 手機回報的定位精度(公尺),事後釐清「為什麼被判成外勤」時使用
            $table->unsignedInteger('clock_in_accuracy')->nullable()->after('clock_in_distance');
            $table->unsignedInteger('clock_out_accuracy')->nullable()->after('clock_out_distance');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['clock_in_accuracy', 'clock_out_accuracy']);
        });
    }
};
