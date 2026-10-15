<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('learner');
            $t->string('plan')->default('free');
            $t->string('learning_level')->default('A1');
            $t->string('translation_language')->default('vi');
            $t->unsignedInteger('daily_word_goal')->default(200);
            $t->timestamp('onboarded_at')->nullable();
        });
        DB::table('users')->where('is_admin', 1)->update(['role' => 'admin']);
        Schema::create('stories', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->string('language');
            $t->string('level');
            $t->string('topic');
            $t->text('summary');
            $t->longText('body');
            $t->json('glossary');
            $t->boolean('is_premium')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('story_enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->unique(['user_id', 'story_id']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_enrollments');
        Schema::dropIfExists('stories');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'plan', 'learning_level', 'translation_language', 'daily_word_goal', 'onboarded_at']));
    }
};
