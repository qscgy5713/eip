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
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->integer('year');
            $table->integer('week_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('summary');
            $table->text('achievements');
            $table->text('plans_next_week');
            $table->text('challenges_or_support')->nullable();
            $table->string('status')->default('submitted'); // draft, submitted, reviewed
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'week_number']);
            $table->index(['department_id', 'year', 'week_number']);
            $table->index('status');
        });

        Schema::create('weekly_report_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_report_id')->constrained('weekly_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();

            $table->index(['weekly_report_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_report_comments');
        Schema::dropIfExists('weekly_reports');
    }
};
