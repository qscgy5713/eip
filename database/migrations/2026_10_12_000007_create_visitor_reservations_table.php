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
        Schema::create('visitor_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_code')->unique(); // 預約通行代碼 VR-YYYYMMDD-XXXX
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete(); // 接待同仁
            $table->string('visitor_name'); // 貴賓姓名
            $table->string('visitor_company')->nullable(); // 來訪單位 / 所屬公司
            $table->string('visitor_email')->nullable(); // 訪客 Email
            $table->string('visitor_phone')->nullable(); // 訪客手機
            $table->date('visit_date'); // 來訪日期
            $table->string('expected_arrival_time')->nullable(); // 預計到達時間 (例：14:00)
            $table->string('purpose'); // business, interview, audit, vendor, other
            $table->foreignId('meeting_room_id')->nullable()->constrained('meeting_rooms')->nullOnDelete();
            $table->unsignedInteger('visitor_count')->default(1); // 同行總人數
            $table->string('status')->default('expected'); // expected, checked_in, checked_out, cancelled
            $table->timestamp('actual_check_in_at')->nullable(); // 前台實際簽到時間
            $table->timestamp('actual_check_out_at')->nullable(); // 實際離廠時間
            $table->string('badge_number')->nullable(); // 發放門禁卡/訪客識別證號
            $table->text('notes')->nullable(); // 接待備註
            $table->timestamps();

            $table->index(['visit_date', 'status']);
            $table->index(['host_id', 'visit_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_reservations');
    }
};
