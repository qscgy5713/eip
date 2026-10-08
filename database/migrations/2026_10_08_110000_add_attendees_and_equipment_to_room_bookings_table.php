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
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->jsonb('equipment_needed')->nullable()->after('attendees_count');
        });

        Schema::create('room_booking_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_booking_id')->constrained('room_bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['room_booking_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_booking_attendees');

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropColumn('equipment_needed');
        });
    }
};
