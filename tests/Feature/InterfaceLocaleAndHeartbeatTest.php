<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InterfaceLocaleAndHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_is_independent_of_learning_pair_and_persists(): void
    {
        $u = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en', 'onboarded_at' => now()]);
        $this->actingAs($u)->post('/interface-locale', ['interface_locale' => 'en'])->assertRedirect('/onboarding');
        $this->assertEquals('en', $u->fresh()->interface_locale);
        $this->assertEquals('spanish', $u->fresh()->selected_language);
        $this->assertEquals('en', $u->fresh()->translation_language);
        $this->get('/onboarding')->assertSee('Interface language')->assertSee('lang="en"', false);
        $this->post('/interface-locale', ['interface_locale' => 'invalid'])->assertSessionHasErrors('interface_locale');
    }

    public function test_active_time_is_bounded_deduplicated_split_at_midnight_and_owner_scoped(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 14)->setTime(16, 59, 55));
        $u = User::factory()->create(['learning_timezone' => 'Asia/Ho_Chi_Minh', 'selected_language' => 'spanish']);
        $book = Book::create(['user_id' => $u->id, 'name' => 'Clock', 'language' => 'spanish']);
        $c = Chapter::forceCreate(['user_id' => $u->id, 'book_id' => $book->id, 'language' => 'spanish', 'name' => 'Clock', 'raw_text' => 'Hola', 'word_count' => 1, 'read_count' => 0, 'unique_words' => '[]', 'unique_word_ids' => '[]', 'subtitle_timestamps' => '', 'type' => 'text', 'processing_status' => 'processed']);
        $url = '/reading-heartbeat/'.$c->id;
        $data = ['tab_id' => (string) Str::uuid(), 'source_hash' => hash('sha256', 'Hola'), 'visible' => true, 'active' => true];
        $this->actingAs($u)->postJson($url, $data)->assertJsonPath('counted_seconds', 0);
        $this->travel(10)->seconds();
        $this->postJson($url, $data)->assertJsonPath('counted_seconds', 10);
        $this->assertDatabaseHas('reading_activity_days', ['day' => '2026-10-14', 'seconds' => 5]);
        $this->assertDatabaseHas('reading_activity_days', ['day' => '2026-10-15', 'seconds' => 5]);
        $this->postJson($url, $data)->assertJsonPath('counted_seconds', 0);
        $otherTab = $data;
        $otherTab['tab_id'] = (string) Str::uuid();
        $this->postJson($url, $otherTab)->assertJsonPath('counted_seconds', 0);
        $this->travel(15)->seconds();
        $this->postJson($url, $data)->assertJsonPath('counted_seconds', 15);
        $this->postJson($url, $otherTab)->assertJsonPath('counted_seconds', 0);
        $this->travel(60)->seconds();
        $this->postJson($url, $data)->assertJsonPath('counted_seconds', 0);
        $this->travel(15)->seconds();
        $this->postJson($url, array_replace($data, ['visible' => false]))->assertJsonPath('counted_seconds', 0);
        $u->forceFill(['learning_timezone' => 'UTC'])->save();
        $this->postJson($url, $data)->assertJsonPath('counted_seconds', 0);
        $this->assertEquals(25, DB::table('reading_activity_days')->sum('seconds'));
        $this->actingAs(User::factory()->create())->postJson($url,$data)->assertNotFound();
        $this->assertDatabaseCount('reading_completions',0);
        $this->travelBack();
    }
}
