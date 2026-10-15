<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoalService;
use Database\Seeders\SpanishStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LearningTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_goal_uses_account_timezone_at_utc_day_boundary(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 18:00:00', 'UTC'));
        $u = User::factory()->create(['selected_language' => 'spanish', 'learning_timezone' => 'Asia/Ho_Chi_Minh']);
        $service = new GoalService;
        $service->createGoalsForLanguage($u->id, 'spanish');
        $service->updateGoalAchievement($u->id, 'spanish', 'read_words', 3);
        $this->assertDatabaseHas('goal_achievements', ['user_id' => $u->id, 'day' => '2026-10-15', 'achieved_quantity' => 3]);
        $this->assertDatabaseMissing('goal_achievements', ['user_id' => $u->id, 'day' => '2026-10-14']);
        $this->travelBack();
    }

    public function test_dashboard_library_and_progress_render_for_onboarded_learner(): void
    {
        $this->seed(SpanishStorySeeder::class);
        $u = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en', 'onboarded_at' => now()]);
        (new GoalService)->createGoalsForLanguage($u->id, 'spanish');
        $this->actingAs($u)->get('/learn')->assertOk()->assertSee('La panadería de Lucía');
        $this->get('/progress')->assertOk()->assertSee('Hoạt động 14 ngày');
        $this->get('/stories?access=free')->assertOk()->assertDontSee('El último tren');
        $this->get('/onboarding')->assertOk()->assertSee('Múi giờ học');
    }
}
