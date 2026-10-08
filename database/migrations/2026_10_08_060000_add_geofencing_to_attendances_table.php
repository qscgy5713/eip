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
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('clock_in_lat', 10, 7)->nullable()->after('clock_in_location');
            $table->decimal('clock_in_lng', 10, 7)->nullable()->after('clock_in_lat');
            $table->integer('clock_in_distance')->nullable()->after('clock_in_lng'); // 與辦公室公尺距離
            $table->string('clock_in_type', 30)->default('office')->after('clock_in_distance'); // office, remote, unverified

            $table->decimal('clock_out_lat', 10, 7)->nullable()->after('clock_out_location');
            $table->decimal('clock_out_lng', 10, 7)->nullable()->after('clock_out_lat');
            $table->integer('clock_out_distance')->nullable()->after('clock_out_lng');
            $table->string('clock_out_type', 30)->default('office')->after('clock_out_distance');

            $table->text('field_work_note')->nullable()->after('note'); // 外勤/出差備註說明
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_lat',
                'clock_in_lng',
                'clock_in_distance',
                'clock_in_type',
                'clock_out_lat',
                'clock_out_lng',
                'clock_out_distance',
                'clock_out_type',
                'field_work_note',
            ]);
        });
    }
};
