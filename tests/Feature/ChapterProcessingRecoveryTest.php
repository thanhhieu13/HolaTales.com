<?php

namespace Tests\Feature;

use App\Events\ChapterStateUpdatedEvent;
use App\Jobs\ProcessChapter;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChapterProcessingRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_realtime_failure_does_not_undo_successful_processing_and_retry_is_owner_scoped(): void
    {
        config(['broadcasting.default' => 'null']);
        $this->seed(SettingsSeeder::class);
        $u = User::factory()->create(['selected_language' => 'spanish']);
        $b = Book::create(['user_id' => $u->id, 'name' => 'Recovery', 'language' => 'spanish']);
        $c = Chapter::forceCreate(['user_id' => $u->id, 'book_id' => $b->id, 'language' => 'spanish', 'name' => 'Recovery', 'raw_text' => 'Ana', 'word_count' => 0, 'read_count' => 0, 'unique_words' => '[]', 'unique_word_ids' => '[]', 'subtitle_timestamps' => '', 'type' => 'text', 'processing_status' => 'unprocessed']);
        Http::fake(['*' => Http::response([['w' => 'Ana', 'l' => 'Ana', 'r' => '', 'lr' => '', 'pos' => 'PROPN', 'si' => 0, 'g' => '']])]);
        Event::listen(ChapterStateUpdatedEvent::class, fn () => throw new BroadcastException('Realtime unavailable.'));
        (new ProcessChapter($u->id, $u->uuid, $c->id, 'spanish'))->handle();
        $this->assertEquals('processed', $c->fresh()->processing_status);
        $this->assertEquals(1, $c->fresh()->word_count);
        $c->processing_status = 'failed';
        $c->save();
        Queue::fake();
        $url = '/chapters/retry-failed-chapters/'.$b->id;
        $this->actingAs(User::factory()->create())->postJson($url)->assertNotFound();
        $this->actingAs($u)->postJson($url)->assertOk();
        $this->postJson($url)->assertOk();
        Queue::assertPushed(ProcessChapter::class, 1);
        $this->getJson($url)->assertMethodNotAllowed();
    }
}
