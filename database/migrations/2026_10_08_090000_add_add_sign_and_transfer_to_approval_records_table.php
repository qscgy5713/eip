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
        Schema::table('approval_records', function (Blueprint $table) {
            $table->foreignId('transferred_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transferred_from_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('add_signed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_add_sign')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transferred_to_id');
            $table->dropConstrainedForeignId('transferred_from_id');
            $table->dropConstrainedForeignId('add_signed_by_id');
            $table->dropColumn(['is_add_sign']);
        });
    }
};
