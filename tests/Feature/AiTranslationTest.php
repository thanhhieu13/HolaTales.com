<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use App\Services\AiTranslationService;
use App\Services\LearningTranslationService;
use Database\Seeders\SpanishStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function enableAi(): void
    {
        config(['learning.translation.provider' => 'ollama']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_ai_translates_full_context_and_replaces_old_machine_cache(): void
    {
        $this->enableAi();
        $text = 'Lucía lee un libro. Después vuelve a casa.';
        DB::table('translation_records')->insert(['content_hash' => hash('sha256', $text), 'source' => 'es', 'target' => 'en', 'provider' => 'MyMemory', 'status' => 'available', 'translation' => 'He reads a book.', 'created_at' => now(), 'updated_at' => now()]);
        Http::fake(['127.0.0.1:11434/*' => Http::response(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode(['paragraphs' => ['Lucía reads a book. Afterwards, she returns home.']])]])]);
        $service = app(LearningTranslationService::class);
        $this->assertStringContainsString('she returns', $service->translate($text, 'es', 'en'));
        $service->translate($text, 'es', 'en');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['messages'][1]['content'] === json_encode(['source_language' => 'es', 'target_language' => 'en', 'source_text' => $text, 'source_paragraphs' => [$text]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) && $r['think'] === false && $r['stream'] === false);
        $this->assertDatabaseHas('translation_records', ['provider' => $service->providerName(), 'status' => 'available']);
    }

    public function test_ai_failure_is_not_cached_or_silently_sent_to_external_provider(): void
    {
        $this->enableAi();
        Http::fake(['*' => Http::response(['error' => 'out of memory'], 503)]);
        try {
            app(LearningTranslationService::class)->translate('Hola', 'es', 'en');
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('unavailable', $e->getMessage());
        }
        $this->assertDatabaseHas('translation_records', ['status' => 'failed']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'http://127.0.0.1:11434/'));
    }

    public function test_partial_ai_response_is_rejected_and_editorial_remains_preferred(): void
    {
        $this->enableAi();
        Http::fake(['*' => Http::response(['done' => true, 'done_reason' => 'length', 'message' => ['content' => '{"translation":"Partial"}']])]);
        $this->seed(SpanishStorySeeder::class);
        $story = Story::where('is_premium', false)->firstOrFail();
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'en'])->assertStatus(503);
        DB::table('story_translations')->where('story_id', $story->id)->update(['status' => 'available', 'review_status' => 'approved', 'body' => 'Editorial translation', 'provider' => 'Editorial']);
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'en'])->assertOk()->assertJsonPath('translation', 'Editorial translation');
        Http::assertSentCount(1);
    }

    public function test_empty_text_and_invalid_ai_output_do_not_become_available(): void
    {
        $this->enableAi();
        Http::fake(['*' => Http::response(['done' => true, 'message' => ['content' => 'not-json']])]);
        try {
            app(LearningTranslationService::class)->translate('  ', 'es', 'en');
            $this->fail('Expected empty input rejection');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Empty', $e->getMessage());
        }
        Http::assertNothingSent();
        try {
            app(LearningTranslationService::class)->translate('Hola', 'es', 'en');
            $this->fail('Expected invalid output rejection');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('invalid', $e->getMessage());
        }
        $this->assertDatabaseMissing('translation_records', ['status' => 'available']);
    }

    public function test_paragraph_alignment_is_preserved_and_remote_ai_url_is_rejected(): void
    {
        $this->enableAi();
        Http::fake(['*' => Http::response(['done' => true, 'message' => ['content' => json_encode(['paragraphs' => ['Hello.', 'Thank you.']])]])]);
        $this->assertSame("Hello.\n\nThank you.", app(LearningTranslationService::class)->translate("Hola.\n\nGracias.", 'es', 'en'));
        config(['learning.translation.ollama_url' => 'http://example.com:11434']);
        try {
            app(AiTranslationService::class)->translate('Hola', 'es', 'en');
            $this->fail('Remote endpoint accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('local inference', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_private_chapter_local_ai_does_not_require_external_upload_consent(): void
    {
        $this->enableAi();
        $user = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en']);
        $book = Book::create(['user_id' => $user->id, 'language' => 'spanish', 'name' => 'Private fixture']);
        $chapter = Chapter::forceCreate(['user_id' => $user->id, 'book_id' => $book->id, 'language' => 'spanish', 'name' => 'One', 'raw_text' => 'Ana lee.', 'word_count' => 2, 'read_count' => 0, 'unique_words' => '[]', 'unique_word_ids' => '[]', 'subtitle_timestamps' => '', 'type' => 'text', 'processing_status' => 'processed']);
        Http::fake(['*' => Http::response(['done' => true, 'message' => ['content' => json_encode(['paragraphs' => ['Ana reads.']])]])]);
        $this->actingAs($user)->postJson('/learning/translate-chapter', ['chapterId' => $chapter->id, 'target' => 'en'])->assertOk()->assertJsonPath('translation', 'Ana reads.');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'http://127.0.0.1:11434/'));
    }
}
