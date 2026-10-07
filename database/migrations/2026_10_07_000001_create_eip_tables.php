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
        // 1. 部門組織表
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('leader_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. 擴充使用者欄位 (組織關係、職稱、角色)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('employee_no')->nullable()->unique();
            $table->string('job_title')->nullable();
            $table->string('role')->default('employee'); // admin, manager, employee, hr
            $table->string('phone')->nullable();
            $table->string('status')->default('active'); // active, suspended, resigned
        });

        // 3. 企業公告表
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('category')->default('general'); // general, company, activity, administrative
            $table->string('priority')->default('normal');  // normal, high, urgent
            $table->boolean('is_pinned')->default(false);
            $table->string('status')->default('published'); // draft, published, archived
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // 4. 公告已讀追蹤表
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });

        // 5. 工作流表單定義表 (Forms Template)
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // LEAVE, EXPENSE, OVERTIME
            $table->text('description')->nullable();
            $table->jsonb('fields_schema')->nullable(); // 動態欄位配置
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. 表單申請單據 (Form Requests)
        Schema::create('form_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_no')->unique(); // e.g. REQ-20261007-0001
            $table->string('title');
            $table->jsonb('data'); // 動態欄位送出資料
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });

        // 7. 簽核歷程記錄表 (Approval Records)
        Schema::create('approval_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_request_id')->constrained('form_requests')->cascadeOnDelete();
            $table->integer('step')->default(1);
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('comment')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_records');
        Schema::dropIfExists('form_requests');
        Schema::dropIfExists('forms');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'employee_no', 'job_title', 'role', 'phone', 'status']);
        });
        Schema::dropIfExists('departments');
    }
};
