<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use Database\Seeders\SpanishStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryAuthIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_story_choice_survives_registration_and_onboarding(): void
    {
        $this->seed(SpanishStorySeeder::class);
        $story = Story::where('is_premium', false)->firstOrFail();
        $this->get('/register?story='.$story->id.'&target=en&next=https://example.com')->assertOk()->assertSessionHas('pending_story', $story->id);
        $this->post('/register', ['name' => 'RC fixture', 'email' => 'rc-intent@example.test', 'password' => 'synthetic-password', 'password_confirmation' => 'synthetic-password'])->assertRedirect('/onboarding');
        $this->post('/onboarding', ['language' => 'spanish', 'translation_language' => 'en', 'level' => 'A1', 'daily_word_goal' => 100])->assertRedirect('/catalogue/'.$story->id.'?target=en')->assertSessionMissing('pending_story');
        $this->assertEquals('free', User::where('email', 'rc-intent@example.test')->firstOrFail()->plan);
    }

    public function test_unpublished_story_cannot_be_saved_as_auth_intent(): void
    {
        $this->seed(SpanishStorySeeder::class);
        $story = Story::firstOrFail();
        $story->published_at = null;
        $story->save();
        $this->get('/register?story='.$story->id)->assertNotFound();
    }
}
