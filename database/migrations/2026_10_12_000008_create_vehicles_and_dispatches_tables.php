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
        // 1. 公務車輛基本資料表
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number')->unique(); // 車牌號碼，例如 ABC-1234
            $table->string('name'); // 車型名稱，例如 Toyota Camry 2.5、Tesla Model Y
            $table->string('vehicle_type')->default('sedan'); // sedan(房車), suv(休旅車), van(商務七人/八人座), truck(小貨車)
            $table->unsignedInteger('capacity')->default(5); // 乘載人數
            $table->string('fuel_type')->default('gasoline'); // gasoline(汽油), hybrid(油電), electric(純電), diesel(柴油)
            $table->unsignedInteger('current_mileage')->default(0); // 目前累積里程數 (km)
            $table->unsignedInteger('last_maintenance_mileage')->default(0); // 上次定期保養里程數 (km)
            $table->string('status')->default('available'); // available(在庫可借), in_use(出勤行駛中), maintenance(保養維修中), inactive(停用)
            $table->string('parking_location')->nullable(); // 車位位置，例如 地下 B2-15 號
            $table->text('notes')->nullable(); // 車輛配備說明或保管注意事項
            $table->timestamps();

            $table->index('status');
        });

        // 2. 公務車借調與出差派車單表
        Schema::create('vehicle_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number')->unique(); // 派車單號 VD-YYYYMMDD-XXXX
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // 申請同仁
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete(); // 借調公務車
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); // 簽核總務/主管
            $table->dateTime('start_time'); // 預計用車開始時間
            $table->dateTime('end_time'); // 預計用車結束時間
            $table->string('destination'); // 前往目的地
            $table->string('purpose'); // 用車目的：client_visit(拜訪客戶), airport_pickup(機場接送), official_errand(公務洽公), vendor_meeting(廠商洽談), other(其他)
            $table->unsignedInteger('passenger_count')->default(1); // 隨行人數
            $table->unsignedInteger('start_mileage')->nullable(); // 出車時儀表里程 (km)
            $table->unsignedInteger('end_mileage')->nullable(); // 歸還時儀表里程 (km)
            $table->string('status')->default('pending'); // pending(待審批), approved(已核准), active(出勤中), completed(已還車), rejected(已駁回), cancelled(已取消)
            $table->text('rejection_reason')->nullable(); // 駁回原因
            $table->timestamp('actual_start_at')->nullable(); // 實際出車時間
            $table->timestamp('actual_return_at')->nullable(); // 實際還車時間
            $table->text('return_notes')->nullable(); // 還車車況回報與加油/電量備註
            $table->timestamps();

            $table->index(['vehicle_id', 'start_time', 'end_time']);
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_dispatches');
        Schema::dropIfExists('vehicles');
    }
};
