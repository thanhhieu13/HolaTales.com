<?php

namespace Tests\Feature;

use App\Models\EncounteredWord;
use App\Models\User;
use App\Services\GoalService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPairGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_tab_and_missing_pair_cannot_write_legacy_meaning(): void
    {
        $user = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en']);
        $word = EncounteredWord::forceCreate(['user_id' => $user->id, 'language' => 'spanish', 'word' => 'hola', 'lemma' => 'hola', 'translation' => 'hello', 'kanji' => '', 'reading' => '', 'base_word' => '', 'base_word_reading' => '', 'stage' => 2]);
        $this->actingAs($user)->postJson('/vocabulary/word/update', ['id' => $word->id, 'translation' => 'wrong'])->assertStatus(409);
        $user->forceFill(['translation_language' => 'vi'])->save();
        $this->withHeaders(['X-Learning-Source' => 'spanish', 'X-Learning-Target' => 'en'])->postJson('/vocabulary/word/update', ['id' => $word->id, 'translation' => 'wrong'])->assertStatus(409);
        $this->assertEquals('hello', $word->fresh()->translation);
        $this->withHeaders(['X-Learning-Target' => 'vi'])->postJson('/vocabulary/word/update', ['id' => $word->id, 'translation' => 'xin chào'])->assertOk();
        $this->assertEquals('xin chào', $word->fresh()->translation);
    }

    public function test_duplicate_phrase_has_one_schedule_and_other_user_is_denied(): void
    {
        $this->seed(SettingsSeeder::class);
        $user = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en']);
        (new GoalService)->createGoalsForLanguage($user->id, 'spanish');
        $headers = ['X-Learning-Source' => 'spanish', 'X-Learning-Target' => 'en'];
        $payload = ['words' => json_encode(['buenos', 'días']), 'stage' => -7, 'reading' => '', 'translation' => 'good morning'];
        $id = $this->actingAs($user)->withHeaders($headers)->postJson('/vocabulary/phrases/create', $payload)->assertOk()->json();
        $this->assertEquals($id, $this->postJson('/vocabulary/phrases/create', $payload)->assertOk()->json());
        $this->assertDatabaseCount('phrases', 1);
        $this->assertDatabaseHas('phrases', ['id' => $id, 'stage' => -7, 'next_review' => now($user->learning_timezone)->toDateString()]);
        $other = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en']);
        $this->actingAs($other)->postJson('/vocabulary/phrases/update', ['id' => $id, 'translation' => 'wrong'])->assertNotFound();
        $this->assertDatabaseHas('phrases', ['id' => $id, 'translation' => 'good morning']);
    }
}
