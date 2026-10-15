<?php

namespace Tests\Feature;

use App\Jobs\TranslateText;
use App\Models\EncounteredWord;
use App\Models\User;
use App\Services\LearningTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WordTranslationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_word_requests_do_not_infer_in_http_and_duplicate_jobs_reuse_cache(): void
    {
        config(['queue.default' => 'redis', 'learning.translation.provider' => 'ollama']);
        Queue::fake();
        Http::preventStrayRequests();
        $user = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en']);
        $word = EncounteredWord::forceCreate(['user_id' => $user->id, 'language' => 'spanish', 'word' => 'hola', 'lemma' => 'hola', 'kanji' => '', 'reading' => '', 'base_word' => '', 'base_word_reading' => '', 'translation' => '', 'stage' => 2]);
        $this->actingAs($user)->withHeaders(['X-Learning-Source' => 'spanish', 'X-Learning-Target' => 'en']);
        $this->postJson('/learning/translate-word', ['wordId' => $word->id])->assertJsonPath('status', 'queued');
        $this->postJson('/learning/translate-word', ['wordId' => $word->id])->assertJsonPath('status', 'queued');
        Queue::assertPushed(TranslateText::class, 1);
        Http::assertNothingSent();
        Http::fake(['127.0.0.1:11434/*' => Http::response(['done' => true, 'message' => ['content' => '{"paragraphs":["hello"]}']])]);
        $job = Queue::pushed(TranslateText::class)->first();
        $job->handle(app(LearningTranslationService::class));
        $job->handle(app(LearningTranslationService::class));
        Http::assertSentCount(1);
        $this->postJson('/learning/translate-word', ['wordId' => $word->id])->assertJsonPath('translation', 'hello');
        $this->assertEquals('', $word->fresh()->translation); // Only learner save writes a personal meaning.
        $this->withHeaders(['X-Learning-Target' => 'vi'])->postJson('/learning/translate-word', ['wordId' => $word->id])->assertStatus(409);
    }
}
