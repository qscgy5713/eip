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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date')->index();
            $table->timestamp('clock_in_at')->nullable();
            $table->string('clock_in_ip')->nullable();
            $table->string('clock_in_location')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->string('clock_out_ip')->nullable();
            $table->string('clock_out_location')->nullable();
            $table->string('status')->default('normal'); // normal, late, early_leave, absent, incomplete
            $table->decimal('work_hours', 5, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
