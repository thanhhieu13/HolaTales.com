<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\StorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LearningSaasTest extends TestCase
{
    use RefreshDatabase;

    private function learner(): User
    {
        return User::factory()->create(['is_admin' => 0, 'role' => 'learner', 'selected_language' => 'english', 'onboarded_at' => now()]);
    }

    public function test_guest_can_sample_but_not_open_premium(): void
    {
        $this->seed(StorySeeder::class);
        $this->get('/')->assertOk()->assertSee('HolaTales');
        $this->get('/try/'.Story::where('is_premium', false)->first()->id)->assertOk();
        $this->get('/try/'.Story::where('is_premium', true)->first()->id)->assertNotFound();
        $this->getJson('/learn')->assertUnauthorized();
    }

    public function test_public_registration_never_grants_admin_or_pro(): void
    {
        $this->post('/register', ['name' => 'Learner', 'email' => 'new@example.test', 'password' => 'secure-password', 'password_confirmation' => 'secure-password', 'isAdmin' => true, 'role' => 'admin', 'plan' => 'pro'])->assertRedirect('/onboarding');
        $u = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertEquals(0, $u->is_admin);
        $this->assertEquals('learner', $u->role);
        $this->assertEquals('free', $u->plan);
        $this->actingAs($u)->post('/onboarding', ['language' => 'english', 'level' => 'A2', 'translation_language' => 'vi', 'daily_word_goal' => 200])->assertRedirect('/learn');
        $this->assertNotNull($u->fresh()->onboarded_at);
        $this->assertDatabaseHas('goals', ['user_id' => $u->id, 'language' => 'english', 'type' => 'read_words', 'quantity' => 200]);
    }

    public function test_user_creation_is_admin_only_even_for_first_user(): void
    {
        $payload = ['name' => 'Escalation', 'email' => 'admin@example.test', 'password' => 'secure-password', 'password_confirmation' => 'secure-password', 'isAdmin' => true];
        $this->postJson('/users/create', $payload)->assertForbidden();
        $u = $this->learner();
        $this->actingAs($u)->postJson('/users/create', $payload)->assertForbidden();
        $u->is_admin = 1;
        $u->role = 'admin';
        $u->save();
        $this->actingAs($u)->postJson('/users/create', $payload)->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'admin@example.test', 'is_admin' => 1]);
    }

    public function test_story_start_is_idempotent_and_private(): void
    {
        Queue::fake();
        $this->seed(StorySeeder::class);
        $u = $this->learner();
        $story = Story::where('is_premium', false)->first();
        $this->actingAs($u)->post('/stories/'.$story->id.'/start')->assertRedirect();
        $this->post('/stories/'.$story->id.'/start')->assertRedirect();
        $this->assertDatabaseCount('story_enrollments', 1);
        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('chapters', 1);
        $book = Book::first();
        $this->actingAs($this->learner())->get('/learn/books/'.$book->id)->assertNotFound();
    }

    public function test_free_cannot_start_pro_and_cannot_edit(): void
    {
        Queue::fake();
        $this->seed(StorySeeder::class);
        $u = $this->learner();
        $story = Story::where('is_premium', true)->first();
        $this->actingAs($u)->post('/stories/'.$story->id.'/start')->assertRedirect('/plans');
        $this->assertDatabaseCount('books', 0);
        $this->get('/editor/stories')->assertForbidden();
        $this->post('/editor/stories/'.$story->id.'/publish')->assertForbidden();
        $u->plan = 'pro';
        $u->save();
        $this->post('/stories/'.$story->id.'/start')->assertRedirect();
        $this->assertDatabaseCount('books', 1);
    }

    public function test_editor_can_publish_but_cannot_manage_users(): void
    {
        $this->seed(StorySeeder::class);
        $u = $this->learner();
        $u->role = 'editor';
        $u->save();
        $story = Story::first();
        $this->actingAs($u)->get('/editor/stories')->assertOk();
        $this->post('/editor/stories/'.$story->id.'/publish')->assertRedirect();
        $this->assertNull($story->fresh()->published_at);
        $this->getJson('/users/get')->assertForbidden();
    }

    public function test_progress_does_not_include_other_learners(): void
    {
        $u = $this->learner();
        $other = $this->learner();
        Book::create(['user_id' => $other->id, 'name' => 'PRIVATE TITLE', 'language' => 'english']);
        $this->actingAs($u)->get('/learn')->assertOk()->assertDontSee('PRIVATE TITLE');
        $this->post('/onboarding', ['language' => 'unsupported', 'level' => 'A1', 'translation_language' => 'vi', 'daily_word_goal' => 200])->assertSessionHasErrors('language');
    }
}
