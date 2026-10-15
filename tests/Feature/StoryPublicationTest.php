<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\User;
use App\Services\CatalogueService;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpanishStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StoryPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordered_publication_preserves_old_enrollments_and_draft_is_not_public(): void
    {
        Queue::fake();
        $this->seed(SpanishStorySeeder::class);
        $this->seed(SettingsSeeder::class);
        $story = Story::where('is_premium', false)->firstOrFail();
        $editor = User::factory()->create(['role' => 'editor']);
        $service = app(CatalogueService::class);
        $service->publish($story, $editor->id);
        $first = StoryChapter::where('story_id', $story->id)->firstOrFail();
        $base = '/editor/stories/'.$story->id;
        $this->actingAs($editor)->post($base.'/chapters', ['title' => 'Second', 'body' => 'Ana vuelve a Madrid.'])->assertRedirect();
        $second = StoryChapter::where('story_id', $story->id)->where('position', 2)->firstOrFail();
        $this->post($base.'/chapters/'.$second->id.'/move', ['direction' => 'up'])->assertRedirect();
        $this->post($base.'/publish', ['action' => 'publish'])->assertRedirect();
        $learner = User::factory()->create(['selected_language' => 'spanish', 'onboarded_at' => now()]);
        $this->actingAs($learner)->post('/stories/'.$story->id.'/start')->assertRedirect();
        $enrollment = DB::table('story_enrollments')->where('user_id', $learner->id)->first();
        $snapshots = Chapter::where('book_id', $enrollment->book_id)->orderBy('id')->get();
        $this->assertCount(2, $snapshots);
        $this->assertEquals('Ana vuelve a Madrid.', $snapshots[0]->raw_text);
        $this->assertNotNull($snapshots[0]->story_chapter_revision_id);
        $this->actingAs($editor)->post($base.'/chapters/'.$second->id.'/edit', ['title' => 'Revised', 'body' => 'PRIVATE NEW DRAFT', 'expected_revision' => 1])->assertRedirect();
        $this->get('/catalogue/'.$story->id)->assertDontSee('PRIVATE NEW DRAFT');
        $this->get('/try/'.$story->id)->assertViewHas('story', fn ($s) => $s->body === 'Ana vuelve a Madrid.');
        $this->post($base.'/publish', ['action' => 'publish'])->assertRedirect();
        $this->assertEquals('Ana vuelve a Madrid.', $snapshots[0]->fresh()->raw_text);
        $this->assertDatabaseCount('story_publications', 3);
        $this->assertEquals($enrollment->story_publication_id, DB::table('story_enrollments')->where('user_id', $learner->id)->value('story_publication_id'));
        $this->post($base.'/chapters/'.$second->id.'/edit', ['title' => 'Stale', 'body' => 'Stale', 'expected_revision' => 1])->assertStatus(409);
        $this->post($base.'/publish', ['action' => 'unpublish'])->assertRedirect();
        $this->get('/catalogue/'.$story->id)->assertNotFound();
        $this->actingAs($learner)->post('/stories/'.$story->id.'/start')->assertNotFound();
    }

    public function test_learner_cannot_edit_chapters_and_approved_chapter_translation_is_reused(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->seed(SpanishStorySeeder::class);
        $story = Story::where('is_premium', false)->firstOrFail();
        $editor = User::factory()->create(['role' => 'editor']);
        app(CatalogueService::class)->publish($story, $editor->id);
        $chapter = StoryChapter::where('story_id', $story->id)->firstOrFail();
        $data = ['target' => 'en', 'body' => 'Approved original chapter.', 'expected_hash' => hash('sha256', $chapter->body)];
        $url = '/editor/stories/'.$story->id.'/chapters/'.$chapter->id.'/translations';
        $this->actingAs(User::factory()->create())->post($url, $data)->assertForbidden();
        $this->actingAs($editor)->post($url, $data)->assertRedirect();
        $this->postJson('/try/'.$story->id.'/translate', ['target' => 'en'])->assertJsonPath('translation', 'Approved original chapter.')->assertJsonPath('review_status', 'approved');
        Http::assertNothingSent();
        $this->post($url.'/revoke', ['target' => 'en', 'expected_hash' => $data['expected_hash']])->assertRedirect();
        $this->assertDatabaseHas('story_chapter_translations', ['story_chapter_id' => $chapter->id, 'status' => 'revoked', 'body' => $data['body']]);
        $this->assertDatabaseHas('story_translations', ['story_id' => $story->id, 'status' => 'revoked']);
        $this->assertDatabaseHas('content_audit_events', ['story_id' => $story->id, 'action' => 'chapter_translation_revoked', 'target' => 'en', 'source_hash' => $data['expected_hash']]);
    }
}
