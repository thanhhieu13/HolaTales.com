<?php

namespace Tests\Feature;

use App\Jobs\TranslateContent;
use App\Models\Story;
use App\Services\ContentTranslationService;
use Database\Seeders\SpanishStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackgroundTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['learning.translation.background' => true, 'queue.default' => 'redis']);
        Queue::fake();
        Http::preventStrayRequests();
        $this->seed(SpanishStorySeeder::class);
    }

    public function test_requests_queue_once_and_worker_persists_translation(): void
    {
        $story = Story::where('is_premium', false)->firstOrFail();
        $story->update(['body' => 'Hola Ana.']);
        $url = '/try/'.$story->id.'/translate';
        $this->postJson($url, ['target' => 'en'])->assertOk()->assertJsonPath('status', 'queued');
        $this->postJson($url, ['target' => 'en'])->assertOk()->assertJsonPath('status', 'queued');
        Queue::assertPushed(TranslateContent::class, 1);
        Http::assertNothingSent();
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'Hello Ana.']])]);
        $job = Queue::pushed(TranslateContent::class)->first();
        $job->handle(app(ContentTranslationService::class));
        $this->postJson($url, ['target' => 'en'])->assertJsonPath('status', 'available')->assertJsonPath('translation', 'Hello Ana.');
        Http::assertSentCount(1);
    }

    public function test_stale_job_does_not_translate_changed_source_and_failure_has_cooldown(): void
    {
        $story = Story::where('is_premium', false)->firstOrFail();
        $url = '/try/'.$story->id.'/translate';
        $this->postJson($url, ['target' => 'en'])->assertJsonPath('status', 'queued');
        $job = Queue::pushed(TranslateContent::class)->first();
        $story->update(['body' => 'Changed original.']);
        $job->handle(app(ContentTranslationService::class));
        Http::assertNothingSent();
        $this->assertDatabaseHas('story_translations', ['source_hash' => $job->hash, 'status' => 'failed']);
        $this->postJson($url, ['target' => 'en'])->assertJsonPath('status', 'queued');
        DB::table('story_translations')->where('source_hash', hash('sha256', $story->body))->update(['status' => 'failed']);
        $this->postJson($url, ['target' => 'en'])->assertJsonPath('status', 'failed');
        Queue::assertPushed(TranslateContent::class, 2);
    }

    public function test_sync_configuration_returns_unavailable_but_approved_content_still_works(): void
    {
        config(['queue.default' => 'sync']);
        $story = Story::where('is_premium', false)->firstOrFail();
        $url = '/try/'.$story->id.'/translate';
        $this->postJson($url, ['target' => 'en'])->assertJsonPath('status', 'unavailable');
        Queue::assertNothingPushed();
        DB::table('story_translations')->insert(['story_id' => $story->id, 'source' => 'es', 'target' => 'en', 'source_hash' => hash('sha256', $story->body), 'status' => 'available', 'review_status' => 'approved', 'provider' => 'Editorial', 'body' => 'Approved.', 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson($url, ['target' => 'en'])->assertJsonPath('translation', 'Approved.');
        Http::assertNothingSent();
    }
}
