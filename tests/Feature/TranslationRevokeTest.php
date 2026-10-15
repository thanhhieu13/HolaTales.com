<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use Database\Seeders\SpanishStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TranslationRevokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_can_be_revoked_only_by_editor_and_is_audited(): void
    {
        $this->seed(SpanishStorySeeder::class);
        $story = Story::where('is_premium', false)->firstOrFail();
        $editor = User::factory()->create(['role' => 'editor']);
        $data = ['target' => 'en', 'expected_hash' => hash('sha256', $story->body)];
        $url = '/editor/stories/'.$story->id.'/translations';
        $this->actingAs($editor)->post($url, $data + ['body' => 'Editorial.'])->assertRedirect();
        $this->actingAs(User::factory()->create())->post($url.'/revoke', $data)->assertForbidden();
        $this->actingAs($editor)->post($url.'/revoke', $data)->assertRedirect();
        $this->assertDatabaseHas('story_translations', ['story_id' => $story->id, 'body' => 'Editorial.', 'review_status' => 'revoked', 'status' => 'revoked']);
        $this->assertDatabaseCount('content_audit_events', 2);
        $this->assertEquals(['translation_approved', 'translation_revoked'], DB::table('content_audit_events')->orderBy('id')->pluck('action')->all());
        $this->post($url.'/revoke', $data)->assertNotFound();
    }
}
