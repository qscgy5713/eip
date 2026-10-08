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
        Schema::table('forms', function (Blueprint $table) {
            $table->jsonb('workflow_config')->nullable()->after('fields_schema');
        });

        Schema::table('form_requests', function (Blueprint $table) {
            $table->integer('total_steps')->default(1)->after('current_step');
            $table->jsonb('workflow_snapshot')->nullable()->after('attachments');
        });

        Schema::table('approval_records', function (Blueprint $table) {
            $table->string('step_title')->nullable()->after('step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_records', function (Blueprint $table) {
            $table->dropColumn('step_title');
        });

        Schema::table('form_requests', function (Blueprint $table) {
            $table->dropColumn(['total_steps', 'workflow_snapshot']);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('workflow_config');
        });
    }
};
