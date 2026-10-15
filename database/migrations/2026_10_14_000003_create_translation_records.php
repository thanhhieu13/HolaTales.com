<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_records', function (Blueprint $t) {
            $t->id();
            $t->char('content_hash', 64);
            $t->string('source', 8);
            $t->string('target', 8);
            $t->string('status')->default('processing');
            $t->string('review_status')->default('unreviewed');
            $t->string('provider');
            $t->longText('translation')->nullable();
            $t->timestamp('retry_at')->nullable();
            $t->timestamps();
            $t->unique(['content_hash', 'source', 'target']);
        });
        Schema::create('reading_completions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('chapter_id');
            $t->string('language');
            $t->unsignedInteger('word_count');
            $t->timestamps();
            $t->unique(['user_id', 'chapter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_completions');
        Schema::dropIfExists('translation_records');
    }
};
