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
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('year');
            $table->integer('month');
            
            // 應發項目 (Earnings)
            $table->decimal('base_salary', 10, 2); // 本薪/底薪
            $table->decimal('allowance', 10, 2)->default(0); // 職務加給/津貼
            $table->decimal('performance_bonus', 10, 2)->default(0); // 績效獎金
            $table->decimal('overtime_pay', 10, 2)->default(0); // 加班津貼
            $table->decimal('other_earnings', 10, 2)->default(0); // 其他應發
            
            // 應扣項目 (Deductions)
            $table->decimal('labor_insurance', 10, 2)->default(0); // 勞保自付額
            $table->decimal('health_insurance', 10, 2)->default(0); // 健保自付額
            $table->decimal('pension_contribution', 10, 2)->default(0); // 勞退個人自提
            $table->decimal('tax_withholding', 10, 2)->default(0); // 代扣所得稅
            $table->decimal('other_deductions', 10, 2)->default(0); // 其他扣除
            
            // 計算結果
            $table->decimal('total_earnings', 10, 2); // 應發合計
            $table->decimal('total_deductions', 10, 2); // 應扣合計
            $table->decimal('net_pay', 10, 2); // 實發金額 (應發 - 應扣)
            
            $table->string('status')->default('draft'); // draft (草稿), published (已發布)
            $table->text('notes')->nullable(); // 人資附註說明
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
            $table->index(['year', 'month', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
