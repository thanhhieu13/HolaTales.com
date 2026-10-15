<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_is_owner_scoped_persistent_and_bound_to_source_version(): void
    {
        $user = User::factory()->create();
        $book = Book::create(['user_id' => $user->id, 'name' => 'Position fixture', 'language' => 'spanish']);
        $chapter = Chapter::forceCreate(['user_id' => $user->id, 'book_id' => $book->id, 'name' => 'Chapter', 'language' => 'spanish', 'type' => 'text', 'word_count' => 2, 'read_count' => 0, 'unique_words' => '[]', 'unique_word_ids' => '[]', 'subtitle_timestamps' => '', 'processing_status' => 'processed', 'raw_text' => 'Hola Ana.']);
        $url = '/reading-position/'.$chapter->id;
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
        $this->postJson($url, ['source_hash' => hash('sha256', $chapter->raw_text), 'scroll_y' => 900])->assertNotFound();
        $this->actingAs($user)->getJson($url)->assertJsonPath('scroll_y', 0);
        $this->postJson($url, ['source_hash' => hash('sha256', $chapter->raw_text), 'scroll_y' => 900])->assertOk();
        $this->getJson($url)->assertJsonPath('scroll_y', 900);
        $this->assertDatabaseCount('reading_positions', 1);
        $chapter->raw_text = 'New original.';
        $chapter->save();
        $this->getJson($url)->assertJsonPath('scroll_y', 0);
        $this->postJson($url, ['source_hash' => hash('sha256', 'Hola Ana.'), 'scroll_y' => 100])->assertStatus(409);
        $this->assertDatabaseHas('reading_positions', ['scroll_y' => 900]);
        $this->assertDatabaseCount('reading_completions', 0);
    }
}
