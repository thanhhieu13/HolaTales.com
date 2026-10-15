<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use Database\Seeders\SpanishStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_approve_targets_without_changing_source_or_using_wrong_target(): void
    {
        $this->seed(SpanishStorySeeder::class);
        $story = Story::where('slug', 'la-panaderia-de-lucia')->first() ?? Story::firstOrFail();
        $editor = User::factory()->create(['role' => 'editor']);
        $original = $story->body;
        $hash = hash('sha256', $original);
        $this->actingAs($editor)->post('/editor/stories/'.$story->id.'/translations', ['target' => 'en', 'body' => 'Lucía visits a bakery.', 'expected_hash' => $hash])->assertRedirect();
        $this->post('/editor/stories/'.$story->id.'/translations', ['target' => 'vi', 'body' => 'Lucía ghé tiệm bánh.', 'expected_hash' => $hash])->assertRedirect();
        Http::fake();
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'en'])->assertOk()->assertJsonPath('translation', 'Lucía visits a bakery.')->assertJsonPath('review_status', 'approved');
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'vi'])->assertOk()->assertJsonPath('translation', 'Lucía ghé tiệm bánh.');
        Http::assertNothingSent();
        $this->assertEquals($original, $story->fresh()->body);
        $this->assertDatabaseCount('story_translations', 2);
    }

    public function test_regular_user_cannot_edit_and_source_edit_invalidates_old_translation(): void
    {
        $this->seed(SpanishStorySeeder::class);
        $story = Story::where('is_premium', false)->firstOrFail();
        $user = User::factory()->create();
        $data = ['target' => 'en', 'body' => 'Old translation', 'expected_hash' => hash('sha256', $story->body)];
        $this->actingAs($user)->post('/editor/stories/'.$story->id.'/translations', $data)->assertForbidden();
        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($editor)->post('/editor/stories/'.$story->id.'/translations', $data)->assertRedirect();
        $this->post('/editor/stories/'.$story->id.'/edit', ['title' => $story->title, 'summary' => $story->summary, 'body' => 'Hola mundo.', 'expected_hash' => $data['expected_hash']])->assertRedirect();
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'Hello world.']])]);
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'en'])->assertOk()->assertJsonPath('translation', 'Hello world.')->assertJsonPath('review_status', 'unreviewed');
        $this->post('/editor/stories/'.$story->id.'/translations', $data)->assertStatus(409);
        $this->assertDatabaseCount('story_translations', 2);
    }
}
