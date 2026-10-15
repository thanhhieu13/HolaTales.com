<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['story' => 'stories', 'chapter' => 'chapters'] as $kind => $parent) {
            Schema::create($kind.'_translations', function (Blueprint $t) use ($kind, $parent) {
                $t->id();
                $t->foreignId($kind.'_id')->constrained($parent)->cascadeOnDelete();
                $t->string('source', 8);
                $t->string('target', 8);
                $t->char('source_hash', 64);
                $t->string('status')->default('processing');
                $t->string('review_status')->default('unreviewed');
                $t->longText('body')->nullable();
                $t->string('provider');
                $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('reviewed_at')->nullable();
                $t->timestamps();
                $t->unique([$kind.'_id', 'source', 'target', 'source_hash'], $kind.'_translation_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_translations');
        Schema::dropIfExists('story_translations');
    }
};
