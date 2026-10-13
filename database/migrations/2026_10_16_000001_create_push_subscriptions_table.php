<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // endpoint 由推播服務(Google/Apple/Mozilla)簽發,長度不定,用雜湊做唯一鍵
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('p256dh');
            $table->string('auth');
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_notifications')->default(true)->after('mail_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_notifications');
        });
        Schema::dropIfExists('push_subscriptions');
    }
};
