<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryChapter;
use Illuminate\Support\Facades\DB;

class CatalogueService
{
    public function ensureOriginal(Story $story): void
    {
        if (! StoryChapter::where('story_id', $story->id)->exists()) {
            $chapter = StoryChapter::create(['story_id' => $story->id, 'position' => 1, 'revision' => 1, 'title' => $story->title, 'language' => $story->language, 'body' => $story->body]);
            $this->recordRevision($chapter);
        }
    }

    public function recordRevision(StoryChapter $chapter, ?int $actor = null): int
    {
        DB::table('story_chapter_revisions')->insertOrIgnore(['story_chapter_id' => $chapter->id, 'revision' => $chapter->revision, 'title' => $chapter->title, 'language' => $chapter->language, 'body' => $chapter->body, 'source_hash' => hash('sha256', $chapter->body), 'created_by' => $actor, 'created_at' => now()]);

        return DB::table('story_chapter_revisions')->where('story_chapter_id', $chapter->id)->where('revision', $chapter->revision)->value('id');
    }

    public function publish(Story $story, int $actor): void
    {
        DB::transaction(function () use ($story, $actor) {
            $story = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            $this->ensureOriginal($story);
            $chapters = StoryChapter::where('story_id', $story->id)->orderBy('position')->get();
            abort_unless($chapters->isNotEmpty() && $chapters->every(fn ($c) => trim($c->body) !== ''), 422, 'Every published chapter needs original text.');
            $version = 1 + (int) DB::table('story_publications')->where('story_id', $story->id)->max('version');
            $meta = $story->only(['title', 'language', 'level', 'topic', 'summary', 'is_premium', 'glossary', 'glossary_language']);
            $id = DB::table('story_publications')->insertGetId(['story_id' => $story->id, 'version' => $version, 'metadata' => json_encode($meta), 'published_by' => $actor, 'created_at' => now()]);
            foreach ($chapters as $index => $chapter) {
                DB::table('story_publication_chapters')->insert(['story_publication_id' => $id, 'story_chapter_revision_id' => $this->recordRevision($chapter, $actor), 'position' => $index + 1]);
            }
            $story->current_publication_id = $id;
            $story->published_at = now();
            $story->save();
            $this->audit($story, $actor, 'published');
        });
    }

    public function publicStory(Story $story): Story
    {
        if (! $story->current_publication_id) {
            return $story;
        } // Legacy fixtures remain readable.
        $publication = DB::table('story_publications')->where('id', $story->current_publication_id)->where('story_id', $story->id)->firstOrFail();
        $copy = clone $story;
        $meta = json_decode($publication->metadata, true);
        if (is_string($meta['glossary'] ?? null)) {
            $meta['glossary'] = json_decode($meta['glossary'], true);
        }
        $copy->forceFill($meta);
        $chapters = DB::table('story_publication_chapters as p')->join('story_chapter_revisions as r', 'r.id', '=', 'p.story_chapter_revision_id')->where('p.story_publication_id', $publication->id)->orderBy('p.position')->select('r.*', 'p.position')->get();
        $copy->body = $chapters->first()?->body ?? '';
        $copy->setRelation('published_chapters', $chapters);
        $copy->setAttribute('publication_version', $publication->version);

        return $copy;
    }

    public function audit(Story $story, int $actor, string $action, ?string $target = null, ?string $hash = null): void
    {
        DB::table('content_audit_events')->insert(['story_id' => $story->id, 'actor_id' => $actor, 'action' => $action, 'target' => $target, 'source_hash' => $hash ?? hash('sha256', $story->body), 'created_at' => now()]);
    }
}
