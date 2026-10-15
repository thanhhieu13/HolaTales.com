<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\EncounteredWord;
use App\Models\Story;
use App\Models\User;
use App\Services\LearningTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LearningTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_translation_does_not_expose_premium_or_draft(): void
    {
        Cache::flush();
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'bản dịch']])]);
        [, $chapter] = $this->fixture();
        $story = Story::first();
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'vi'])->assertOk();
        $story->is_premium = true;
        $story->save();
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'vi'])->assertNotFound();
        $story->is_premium = false;
        $story->published_at = null;
        $story->save();
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'vi'])->assertNotFound();
        Http::assertSentCount(1);
    }

    private function fixture(bool $curated = true): array
    {
        $u = User::factory()->create(['is_admin' => 0, 'selected_language' => 'english', 'translation_language' => 'vi', 'onboarded_at' => now()]);
        $b = Book::create(['user_id' => $u->id, 'language' => 'english', 'name' => 'Translation fixture']);
        $c = new Chapter(['user_id' => $u->id, 'book_id' => $b->id, 'name' => 'Fixture', 'language' => 'english', 'raw_text' => 'A little bird sings.', 'word_count' => 4, 'read_count' => 0]);
        $c->unique_words = '[]';
        $c->unique_word_ids = '[]';
        $c->subtitle_timestamps = '';
        $c->type = 'text';
        $c->processing_status = 'processed';
        $c->save();
        if ($curated) {
            $s = Story::create(['slug' => 'test-story', 'title' => 'Test story', 'language' => 'english', 'level' => 'A1', 'topic' => 'nature', 'summary' => 'test', 'body' => $c->raw_text, 'glossary_language' => 'vi', 'glossary' => ['bird' => 'chim'], 'published_at' => now()]);
            DB::table('story_enrollments')->insert(['user_id' => $u->id, 'story_id' => $s->id, 'book_id' => $b->id]);
        }

        return [$u, $c];
    }

    public function test_curated_story_translates_and_caches(): void
    {
        Cache::flush();
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'Một chú chim nhỏ hót.']])]);
        [$u,$c] = $this->fixture();
        $this->actingAs($u)->postJson('/learning/translate-chapter', ['chapterId' => $c->id])->assertOk()->assertJsonPath('translation', 'Một chú chim nhỏ hót.');
        $this->postJson('/learning/translate-chapter', ['chapterId' => $c->id])->assertOk();
        Http::assertSentCount(1);
    }

    public function test_private_story_requires_consent_and_other_user_is_denied(): void
    {
        Http::fake();
        [$u,$c] = $this->fixture(false);
        $this->actingAs($u)->postJson('/learning/translate-chapter', ['chapterId' => $c->id])->assertStatus(409)->assertJsonPath('requiresConsent', true);
        Http::assertNothingSent();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/learning/translate-chapter', ['chapterId' => $c->id, 'allow_external' => true])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_quota_failure_is_not_cached_or_shown_as_translation(): void
    {
        Cache::flush();
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 403, 'quotaFinished' => true, 'responseData' => ['translatedText' => 'LIMIT EXCEEDED']])]);
        [$u,$c] = $this->fixture();
        $this->actingAs($u)->postJson('/learning/translate-chapter', ['chapterId' => $c->id])->assertStatus(503)->assertJsonMissing(['translation' => 'LIMIT EXCEEDED']);
    }

    public function test_glossary_word_is_local_and_private(): void
    {
        Http::fake();
        [$u,$c] = $this->fixture();
        $w = new EncounteredWord(['user_id' => $u->id, 'language' => 'english', 'word' => 'bird', 'stage' => 2, 'translation' => '', 'kanji' => '', 'reading' => '', 'base_word' => '', 'base_word_reading' => '', 'lookup_count' => 0, 'read_count' => 0]);
        $w->lemma = 'bird';
        $w->save();
        $this->actingAs($u)->withHeaders(['X-Learning-Source' => $u->selected_language, 'X-Learning-Target' => $u->translation_language])->postJson('/learning/translate-word', ['wordId' => $w->id])->assertOk()->assertJsonPath('translation', 'chim');
        Http::assertNothingSent();
        $this->actingAs(User::factory()->create())->postJson('/learning/translate-word', ['wordId' => $w->id])->assertNotFound();
    }

    public function test_segments_are_bounded_and_invalid_payload_is_rejected(): void
    {
        Cache::flush();
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'bản dịch']])]);
        app(LearningTranslationService::class)->translate(str_repeat('A little bird sings. ', 40));
        Http::assertSent(fn ($r) => strlen($r['q']) <= 450);
        $this->assertGreaterThan(1, count(Http::recorded()));
        $this->actingAs(User::factory()->create())->postJson('/learning/translate-chapter', [])->assertUnprocessable();
    }
}
