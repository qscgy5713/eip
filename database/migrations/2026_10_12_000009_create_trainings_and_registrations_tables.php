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
        // 1. 企業內訓課程主表
        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_code')->unique(); // 課程代碼 TC-YYYYMM-XXXX
            $table->string('title'); // 課程主題名稱
            $table->text('description')->nullable(); // 課程大綱與學習目標
            $table->string('instructor'); // 講師姓名 / 外部專家顧問
            $table->string('instructor_title')->nullable(); // 講師背景 / 單位職稱
            $table->string('category')->default('technical'); // technical(技術研發), management(管理領導), compliance(法規資安), general(職場通識)
            $table->string('location')->default('總部大型培訓教室 (3F)'); // 上課地點或視訊會議連結
            $table->dateTime('start_time'); // 培訓開始時間
            $table->dateTime('end_time'); // 培訓結束時間
            $table->unsignedInteger('max_participants')->default(30); // 名額上限 (0 為無限制)
            $table->unsignedInteger('credits')->default(2); // 認證學分 (點數)
            $table->decimal('hours', 4, 1)->default(2.0); // 研習認證時數
            $table->string('status')->default('upcoming'); // draft(草稿), upcoming(報名中), in_progress(授課中), completed(已完訓), cancelled(已停辦)
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete(); // 主辦人資/行政
            $table->timestamps();

            $table->index(['status', 'start_time']);
            $table->index('category');
        });

        // 2. 課程學員報名與完訓簽到紀錄表
        Schema::create('course_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('registered'); // registered(已報名), waitlist(候補名單), attended(完訓合格), absent(曠職未到), cancelled(已取消)
            $table->dateTime('registered_at')->useCurrent(); // 報名登記時間
            $table->dateTime('attended_at')->nullable(); // 簽到/主管核發完訓時間
            $table->unsignedTinyInteger('rating')->nullable(); // 滿意度回饋 1~5 星
            $table->text('feedback')->nullable(); // 課後心得與回饋意見
            $table->timestamps();

            $table->unique(['training_course_id', 'user_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_registrations');
        Schema::dropIfExists('training_courses');
    }
};
