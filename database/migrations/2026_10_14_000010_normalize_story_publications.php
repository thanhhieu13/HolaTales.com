<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_chapters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position');
            $t->unsignedInteger('revision')->default(1);
            $t->string('title');
            $t->string('language');
            $t->longText('body');
            $t->timestamps();
            $t->unique(['story_id', 'position']);
        });
        Schema::create('story_chapter_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_chapter_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision');
            $t->string('title');
            $t->string('language');
            $t->longText('body');
            $t->char('source_hash', 64);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
            $t->unique(['story_chapter_id', 'revision']);
        });
        Schema::create('story_publications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('metadata');
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
            $t->unique(['story_id', 'version']);
        });
        Schema::create('story_publication_chapters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_publication_id')->constrained()->cascadeOnDelete();
            $t->foreignId('story_chapter_revision_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('position');
            $t->unique(['story_publication_id', 'position'], 'publication_chapter_position_unique');
        });
        Schema::create('story_chapter_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_chapter_id')->constrained()->cascadeOnDelete();
            $t->string('source', 8);
            $t->string('target', 8);
            $t->char('source_hash', 64);
            $t->string('status')->default('queued');
            $t->string('review_status')->default('unreviewed');
            $t->longText('body')->nullable();
            $t->string('provider');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['story_chapter_id', 'source', 'target', 'source_hash'], 'original_chapter_translation_version_unique');
        });
        Schema::table('stories', fn (Blueprint $t) => $t->foreignId('current_publication_id')->nullable()->constrained('story_publications')->nullOnDelete());
        Schema::table('chapters', fn (Blueprint $t) => $t->foreignId('story_chapter_revision_id')->nullable()->constrained()->nullOnDelete());
        Schema::table('story_enrollments', fn (Blueprint $t) => $t->foreignId('story_publication_id')->nullable()->constrained()->nullOnDelete());
        // Existing originals become one ordered chapter. Existing learner books are not rewritten.
        foreach (DB::table('stories')->orderBy('id')->get() as $s) {
            $chapterId = DB::table('story_chapters')->insertGetId(['story_id' => $s->id, 'position' => 1, 'revision' => 1, 'title' => $s->title, 'language' => $s->language, 'body' => $s->body, 'created_at' => now(), 'updated_at' => now()]);
            $revisionId = DB::table('story_chapter_revisions')->insertGetId(['story_chapter_id' => $chapterId, 'revision' => 1, 'title' => $s->title, 'language' => $s->language, 'body' => $s->body, 'source_hash' => hash('sha256', $s->body), 'created_at' => now()]);
            if ($s->published_at) {
                $meta = array_intersect_key((array) $s, array_flip(['title', 'language', 'level', 'topic', 'summary', 'is_premium', 'glossary', 'glossary_language']));
                $publicationId = DB::table('story_publications')->insertGetId(['story_id' => $s->id, 'version' => 1, 'metadata' => json_encode($meta), 'created_at' => $s->published_at]);
                DB::table('story_publication_chapters')->insert(['story_publication_id' => $publicationId, 'story_chapter_revision_id' => $revisionId, 'position' => 1]);
                DB::table('stories')->where('id', $s->id)->update(['current_publication_id' => $publicationId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('story_enrollments', fn (Blueprint $t) => $t->dropConstrainedForeignId('story_publication_id'));
        Schema::table('chapters', fn (Blueprint $t) => $t->dropConstrainedForeignId('story_chapter_revision_id'));
        Schema::table('stories', fn (Blueprint $t) => $t->dropConstrainedForeignId('current_publication_id'));
        Schema::dropIfExists('story_chapter_translations');
        Schema::dropIfExists('story_publication_chapters');
        Schema::dropIfExists('story_publications');
        Schema::dropIfExists('story_chapter_revisions');
        Schema::dropIfExists('story_chapters');
    }
};
