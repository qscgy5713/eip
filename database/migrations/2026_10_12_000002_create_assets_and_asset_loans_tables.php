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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique(); // 資產編號 (如 AST-2026-0001)
            $table->string('name'); // 資產名稱
            $table->string('category')->default('it_hardware'); // it_hardware, office_equipment, av_media, vehicles, peripherals
            $table->string('serial_number')->nullable(); // 原廠序號
            $table->text('specification')->nullable(); // 規格/配置說明
            $table->string('location')->nullable(); // 存放地點
            $table->string('status')->default('available'); // available, in_use, maintenance, retired
            $table->foreignId('current_holder_id')->nullable()->constrained('users')->nullOnDelete(); // 目前保管人
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete(); // 歸屬部門
            $table->date('purchase_date')->nullable(); // 採購日期
            $table->decimal('purchase_cost', 10, 2)->nullable(); // 採購金額
            $table->text('notes')->nullable(); // 備註
            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index('status');
        });

        Schema::create('asset_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('borrowed_at');
            $table->date('expected_return_date')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('status')->default('borrowed'); // borrowed, returned
            $table->text('purpose')->nullable();
            $table->text('return_notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['asset_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_loans');
        Schema::dropIfExists('assets');
    }
};
