<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('interface_locale', 8)->default('vi');
            $t->timestamp('reading_clock_at')->nullable();
        });
        Schema::create('reading_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $t->uuid('tab_id');
            $t->char('source_hash', 64);
            $t->string('timezone');
            $t->boolean('active')->default(false);
            $t->timestamp('last_heartbeat_at');
            $t->unique(['user_id', 'tab_id']);
        });
        Schema::create('reading_activity_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('language');
            $t->date('day');
            $t->string('timezone');
            $t->unsignedInteger('seconds')->default(0);
            $t->unique(['user_id', 'language', 'day', 'timezone'], 'active_reading_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_activity_days');
        Schema::dropIfExists('reading_sessions');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['interface_locale', 'reading_clock_at']));
    }
};
