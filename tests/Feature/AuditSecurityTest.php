<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\EncounteredWord;
use App\Models\User;
use App\Services\ChapterService;
use App\Services\GoalService;
use App\Services\LearningTranslationService;
use App\Services\SafeWebsiteService;
use App\Services\UserService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_urls_are_rejected_before_request(): void
    {
        Http::fake();
        foreach (['http://127.0.0.1/admin', 'http://10.0.0.1', 'http://169.254.169.254/', 'file:///etc/passwd', 'http://user:pass@example.com', 'http://example.com:3306'] as $url) {
            try {
                app(SafeWebsiteService::class)->fetch($url);
                $this->fail('Unsafe URL accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('url', $e->errors());
            }
        }
        Http::assertNothingSent();
    }

    public function test_translation_is_persistent_and_pair_specific(): void
    {
        Cache::flush();
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'bread']])]);
        $t = app(LearningTranslationService::class);
        $this->assertEquals('bread', $t->translate('pan', 'es', 'en'));
        Cache::flush();
        $this->assertEquals('bread', $t->translate('pan', 'es', 'en'));
        Http::assertSentCount(1);
        $this->assertDatabaseHas('translation_records', ['source' => 'es', 'target' => 'en', 'status' => 'available', 'review_status' => 'unreviewed']);
    }

    public function test_duplicate_completion_does_not_increase_progress(): void
    {
        $this->seed(SettingsSeeder::class);
        $u = User::factory()->create(['selected_language' => 'spanish']);
        (new GoalService)->createGoalsForLanguage($u->id, 'spanish');
        $b = Book::create(['user_id' => $u->id, 'language' => 'spanish', 'name' => 'Completion']);
        $c = new Chapter(['user_id' => $u->id, 'book_id' => $b->id, 'name' => 'One', 'language' => 'spanish', 'raw_text' => 'Hola mundo', 'word_count' => 2, 'read_count' => 0]);
        $c->unique_words = '[]';
        $c->unique_word_ids = '[]';
        $c->subtitle_timestamps = '';
        $c->type = 'text';
        $c->processing_status = 'processed';
        $c->save();
        $service = new ChapterService;
        foreach ([1, 2] as $_) {
            $service->finishChapter($u->id, $c->id, false, [], false, [], [], 'spanish');
        }
        $this->assertEquals(1, $c->fresh()->read_count);
        $this->assertDatabaseCount('reading_completions', 1);
        $this->assertDatabaseHas('goal_achievements', ['user_id' => $u->id, 'achieved_quantity' => 2]);
    }

    public function test_password_change_requires_current_password_and_import_invalid_type_is_422(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/users/update-password', ['password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password'])->assertUnprocessable();
        $this->postJson('/import', ['importType' => 'invalid'])->assertUnprocessable();
    }

    public function test_import_does_not_create_partial_books_on_parser_failure_or_foreign_book(): void
    {
        $u = User::factory()->create(['selected_language' => 'spanish']);
        $payload = ['importType' => 'plain-text', 'importText' => 'Hola mundo.', 'bookId' => -1,
            'bookName' => 'Personal', 'chapterName' => 'One', 'maximumCharactersPerChapter' => 3000,
            'textProcessingMethod' => 'detailed', 'eBookChapterSortMethod' => 'default'];
        Http::fake(['*' => Http::sequence()->push([], 500)->push(['Hola mundo.'])]);
        $this->actingAs($u)->postJson('/import', $payload)->assertStatus(502);
        $this->assertDatabaseCount('books', 0);
        $other = User::factory()->create();
        $book = Book::create(['user_id' => $other->id, 'language' => 'spanish', 'name' => 'Private']);
        $payload['bookId'] = $book->id;
        $this->postJson('/import', $payload)->assertNotFound();
        $this->assertDatabaseCount('chapters', 0);
    }

    public function test_language_deletion_clears_pair_archives_and_completion_events_only_for_owner(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();
        $word = EncounteredWord::forceCreate(['user_id' => $u->id, 'language' => 'spanish', 'word' => 'pan', 'lemma' => 'pan', 'kanji' => '', 'reading' => '', 'base_word' => '', 'base_word_reading' => '', 'translation' => 'bread', 'stage' => 2]);
        foreach ([$u, $other] as $owner) {
            DB::table('saved_meanings')->insert(['user_id' => $owner->id, 'kind' => 'word', 'item_id' => $word->id, 'target' => 'en', 'translation' => 'note']);
            DB::table('reading_completions')->insert(['user_id' => $owner->id, 'chapter_id' => 99, 'language' => 'spanish', 'word_count' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        app(UserService::class)->deleteUserLanguageData($u->id, 'spanish');
        $this->assertDatabaseMissing('saved_meanings', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('reading_completions', ['user_id' => $u->id]);
        $this->assertDatabaseHas('saved_meanings', ['user_id' => $other->id]);
        $this->assertDatabaseHas('reading_completions', ['user_id' => $other->id]);
    }

    public function test_backup_requires_post_and_admin_without_creating_real_dump(): void
    {
        $u = User::factory()->create(['is_admin' => false]);
        $this->actingAs($u)->get('/backups/create')->assertStatus(405);
        $this->postJson('/backups/create')->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        Artisan::shouldReceive('call')->once()->with('app:create-backup')->andReturn(0);
        $this->actingAs($admin)->postJson('/backups/create')->assertOk()->assertJsonPath('exitCode', 0);
    }
}
