<?php

namespace Tests\Feature;

use App\Models\EncounteredWord;
use App\Models\Goal;
use App\Models\User;
use App\Services\GoalService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlashcardReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(SettingsSeeder::class);
        $u = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en']);
        (new GoalService)->createGoalsForLanguage($u->id, 'spanish');
        $word = EncounteredWord::forceCreate(['user_id' => $u->id, 'language' => 'spanish', 'word' => 'pan', 'translation' => 'bread', 'lemma' => 'pan', 'kanji' => '', 'reading' => '', 'base_word' => '', 'base_word_reading' => '', 'stage' => -7, 'relearning' => false]);
        $word->next_review = today()->toDateString();
        $word->save();

        return [$u, $word, ['request_id' => (string) Str::uuid(), 'kind' => 'word', 'item_id' => $word->id, 'source' => 'spanish', 'target' => 'en', 'grade' => 'good', 'expected_stage' => -7, 'expected_relearning' => false]];
    }

    public function test_repeated_submission_is_one_persistent_review_and_goal(): void
    {
        [$u,$w,$data] = $this->fixture();
        $this->actingAs($u)->postJson('/reviews', ['practiceMode' => false, 'bookId' => -1, 'chapterId' => -1])->assertOk()->assertJsonPath('target', 'en')->assertJsonCount(1, 'reviews');
        $this->postJson('/reviews/submit', $data)->assertOk()->assertJsonPath('stage', -6);
        $this->postJson('/reviews/submit', $data)->assertOk()->assertJsonPath('stage', -6);
        $this->assertDatabaseCount('flashcard_reviews', 1);
        $this->assertEquals(-6, $w->fresh()->stage);
        $goal = Goal::where('user_id', $u->id)->where('type', 'review')->firstOrFail();
        $this->assertDatabaseHas('goal_achievements', ['goal_id' => $goal->id, 'achieved_quantity' => 1]);
        $data['request_id'] = (string) Str::uuid();
        $this->postJson('/reviews/submit', $data)->assertStatus(409);
    }

    public function test_other_user_and_stale_pair_cannot_submit_card(): void
    {
        [$u,$w,$data] = $this->fixture();
        $other = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en']);
        $this->actingAs($other)->postJson('/reviews/submit', $data)->assertNotFound();
        $data['target'] = 'vi';
        $this->actingAs($u)->postJson('/reviews/submit', $data)->assertStatus(409);
        $this->assertEquals(-7, $w->fresh()->stage);
        $this->assertDatabaseCount('flashcard_reviews', 0);
    }

    public function test_grades_use_existing_schedule_without_resetting_other_cards(): void
    {
        [$u,$w,$data] = $this->fixture();
        foreach (['hard' => -7, 'good' => -6, 'easy' => -5, 'again' => -7] as $grade => $stage) {
            $w->refresh();
            $w->stage = -7;
            $w->next_review = today()->toDateString();
            $w->relearning = false;
            $w->save();
            $data['request_id'] = (string) Str::uuid();
            $data['grade'] = $grade;
            $this->actingAs($u)->postJson('/reviews/submit', $data)->assertOk()->assertJsonPath('stage', $stage);
        }
        $this->assertDatabaseCount('flashcard_reviews', 4);
        $this->postJson('/reviews/update', ['readWords' => 9999])->assertStatus(409);
    }
}
