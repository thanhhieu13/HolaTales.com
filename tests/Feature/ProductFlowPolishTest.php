<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\SpanishStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductFlowPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_details_are_public_but_drafts_and_other_user_progress_are_not(): void
    {
        $this->seed(SpanishStorySeeder::class);
        $story = Story::where('is_premium', false)->firstOrFail();
        $this->get('/catalogue/'.$story->id)->assertOk()->assertSee($story->title)->assertSee('Đọc thử không cần tài khoản');
        $this->get('/catalogue/'.$story->id.'?target=xx')->assertRedirect();
        $story->published_at = null;
        $story->save();
        $this->get('/catalogue/'.$story->id)->assertNotFound();
    }

    public function test_continue_reading_uses_first_unfinished_chapter_and_requires_ownership(): void
    {
        $u = User::factory()->create(['selected_language' => 'spanish', 'onboarded_at' => now()]);
        $other = User::factory()->create();
        $book = Book::create(['user_id' => $u->id, 'language' => 'spanish', 'name' => 'Multi chapter']);
        $base = ['user_id' => $u->id, 'book_id' => $book->id, 'language' => 'spanish', 'raw_text' => 'Hola', 'word_count' => 1, 'unique_words' => '[]', 'unique_word_ids' => '[]', 'subtitle_timestamps' => '', 'type' => 'text', 'processing_status' => 'processed'];
        Chapter::forceCreate($base + ['name' => 'First', 'read_count' => 1]);
        $second = Chapter::forceCreate($base + ['name' => 'Second', 'read_count' => 0]);
        $this->actingAs($u)->get('/learn/books/'.$book->id)->assertRedirect('/chapters/read/'.$second->id);
        $this->actingAs($other)->get('/learn/books/'.$book->id)->assertNotFound();
    }

    public function test_detail_continue_link_is_scoped_to_current_learner(): void
    {
        $this->seed(SpanishStorySeeder::class);
        $story = Story::where('is_premium', false)->firstOrFail();
        $u = User::factory()->create(['selected_language' => 'spanish']);
        $other = User::factory()->create(['selected_language' => 'spanish']);
        $book = Book::create(['user_id' => $u->id, 'language' => 'spanish', 'name' => 'Snapshot']);
        Chapter::forceCreate(['user_id' => $u->id, 'book_id' => $book->id, 'language' => 'spanish', 'name' => 'One', 'raw_text' => 'Hola', 'word_count' => 1, 'read_count' => 0, 'unique_words' => '[]', 'unique_word_ids' => '[]', 'subtitle_timestamps' => '', 'type' => 'text', 'processing_status' => 'processed']);
        DB::table('story_enrollments')->insert(['user_id' => $u->id, 'story_id' => $story->id, 'book_id' => $book->id]);
        $this->actingAs($u)->get('/catalogue/'.$story->id)->assertOk()->assertSee('/learn/books/'.$book->id, false);
        $this->actingAs($other)->get('/catalogue/'.$story->id)->assertOk()->assertDontSee('/learn/books/'.$book->id, false);
    }
}
