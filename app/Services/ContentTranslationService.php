<?php

namespace App\Services;

use App\Jobs\TranslateContent;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

class ContentTranslationService
{
    public function __construct(private LearningTranslationService $translator) {}

    public function request(Story|Chapter $content, string $target): array
    {
        if (! config('learning.translation.background')) {
            return $this->translate($content, $target) + ['status' => 'available'];
        }
        $kind = $content instanceof Story ? 'story' : 'chapter';
        $text = $kind === 'story' ? $content->body : $content->raw_text;
        $source = config('learning.sources.'.$content->language.'.code');
        $identity = [$kind.'_id' => $content->id, 'source' => $source, 'target' => $target, 'source_hash' => hash('sha256', $text)];
        $provider = $this->translator->providerName();
        $old = DB::table($kind.'_translations')->where($identity)->first();
        if ($approved = $this->approvedOriginal($content, $source, $target, $identity['source_hash'])) {
            $this->persist($kind, $identity, ['body' => $approved->body, 'status' => 'available', 'provider' => $approved->provider, 'review_status' => 'approved', 'reviewed_by' => $approved->reviewed_by, 'reviewed_at' => $approved->reviewed_at]);

            return ['status' => 'available', 'translation' => $approved->body, 'provider' => $approved->provider, 'review_status' => 'approved'];
        }
        if ($old && $old->status === 'available' && ($old->review_status === 'approved' || $old->provider === $provider)) {
            return ['status' => 'available', 'translation' => $old->body, 'provider' => $old->provider, 'review_status' => $old->review_status];
        }
        if ($kind === 'chapter') {
            $storyId = DB::table('story_enrollments')->where('user_id', $content->user_id)->where('book_id', $content->book_id)->value('story_id');
            $approved = $storyId ? DB::table('story_translations')->where('story_id', $storyId)->where('source', $source)->where('target', $target)->where('source_hash', $identity['source_hash'])->where('status', 'available')->where('review_status', 'approved')->first() : null;
            if ($approved) {
                $this->persist($kind, $identity, ['body' => $approved->body, 'status' => 'available', 'provider' => $approved->provider, 'review_status' => 'approved', 'reviewed_by' => $approved->reviewed_by, 'reviewed_at' => $approved->reviewed_at]);

                return ['status' => 'available', 'translation' => $approved->body, 'provider' => $approved->provider, 'review_status' => 'approved'];
            }
        }
        if (config('queue.default') === 'sync') {
            return ['status' => 'unavailable', 'message' => 'Translation worker is not configured.'];
        }
        if ($old && $old->provider === $provider && $old->status === 'failed' && now()->diffInSeconds($old->updated_at, true) < 60) {
            return ['status' => 'failed', 'message' => 'Translation failed. Retry after one minute.'];
        }
        if ($old && $old->provider === $provider && in_array($old->status, ['queued', 'processing']) && now()->diffInSeconds($old->updated_at, true) < 600) {
            return ['status' => $old->status];
        }
        $this->persist($kind, $identity, ['status' => 'queued', 'provider' => $provider]);
        try {
            TranslateContent::dispatch($kind, $content->id, $target, $identity['source_hash'], $provider);
        } catch (\Throwable $error) {
            $this->persist($kind, $identity, ['status' => 'failed', 'provider' => $provider]);

            return ['status' => 'unavailable', 'message' => 'Translation queue is unavailable.'];
        }

        return ['status' => 'queued'];
    }

    public function translate(Story|Chapter $content, string $target): array
    {
        $kind = $content instanceof Story ? 'story' : 'chapter';
        $text = $kind === 'story' ? $content->body : $content->raw_text;
        $source = config('learning.sources.'.$content->language.'.code');
        $identity = [$kind.'_id' => $content->id, 'source' => $source, 'target' => $target, 'source_hash' => hash('sha256', $text)];
        $query = DB::table($kind.'_translations')->where($identity);
        $old = $query->first();
        if ($approved = $this->approvedOriginal($content, $source, $target, $identity['source_hash'])) {
            $this->persist($kind, $identity, ['body' => $approved->body, 'status' => 'available', 'provider' => $approved->provider, 'review_status' => 'approved', 'reviewed_by' => $approved->reviewed_by, 'reviewed_at' => $approved->reviewed_at]);

            return ['translation' => $approved->body, 'provider' => $approved->provider, 'review_status' => 'approved'];
        }
        // A learner's unchanged copy can reuse an approved catalogue translation.
        if ($kind === 'chapter') {
            $storyId = DB::table('story_enrollments')->where('user_id', $content->user_id)->where('book_id', $content->book_id)->value('story_id');
            $approved = $storyId ? DB::table('story_translations')->where('story_id', $storyId)->where('source', $source)->where('target', $target)->where('source_hash', $identity['source_hash'])->where('status', 'available')->where('review_status', 'approved')->first() : null;
            if ($approved) {
                $this->persist($kind, $identity, ['body' => $approved->body, 'status' => 'available', 'provider' => $approved->provider, 'review_status' => 'approved', 'reviewed_by' => $approved->reviewed_by, 'reviewed_at' => $approved->reviewed_at]);

                return ['translation' => $approved->body, 'provider' => $approved->provider, 'review_status' => 'approved'];
            }
        }
        if ($old && $old->status === 'available' && ($old->review_status === 'approved' || $old->provider === $this->translator->providerName())) {
            return ['translation' => $old->body, 'provider' => $old->provider, 'review_status' => $old->review_status];
        }
        $this->persist($kind, $identity, ['status' => 'processing', 'provider' => $this->translator->providerName()]);
        try {
            $translation = $this->translator->translate($text, $source, $target);
            $this->persist($kind, $identity, ['body' => $translation, 'status' => 'available', 'review_status' => 'unreviewed', 'reviewed_by' => null, 'reviewed_at' => null, 'provider' => $this->translator->providerName()]);
            $saved = DB::table($kind.'_translations')->where($identity)->first();

            return ['translation' => $saved->body, 'provider' => $saved->provider, 'review_status' => $saved->review_status];
        } catch (\Throwable $e) {
            $this->persist($kind, $identity, ['status' => 'failed', 'provider' => $this->translator->providerName()]);
            throw $e;
        }
    }

    private function approvedOriginal(Story|Chapter $content, string $source, string $target, string $hash): ?object
    {
        $id = null;
        if ($content instanceof Chapter && $content->story_chapter_revision_id) {
            $id = DB::table('story_chapter_revisions')->where('id', $content->story_chapter_revision_id)->where('source_hash', $hash)->value('story_chapter_id');
        } elseif ($content instanceof Story && $content->relationLoaded('published_chapters')) {
            $id = $content->getRelation('published_chapters')->first()?->story_chapter_id;
        }

        return $id ? DB::table('story_chapter_translations')->where('story_chapter_id', $id)->where('source', $source)->where('target', $target)->where('source_hash', $hash)->where('status', 'available')->where('review_status', 'approved')->first() : null;
    }

    private function persist(string $kind, array $identity, array $values): void
    {
        $table = DB::table($kind.'_translations');
        $table->insertOrIgnore($identity + $values + ['created_at' => now(), 'updated_at' => now()]);
        // A provider finishing late must never overwrite an editorial approval.
        $table->where($identity)->where('review_status', '!=', 'approved')->update($values + ['updated_at' => now()]);
    }
}
