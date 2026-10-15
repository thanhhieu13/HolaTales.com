<?php

namespace Tests\Feature;

use App\Models\EncounteredWord;
use App\Models\Story;
use App\Models\User;
use App\Services\LearningTranslationService;
use Database\Seeders\SpanishStorySeeder;
use Database\Seeders\StorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MultilingualLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_prioritize_spanish_english_and_catalogue_filters(): void
    {
        Queue::fake();
        $this->seed([SpanishStorySeeder::class, StorySeeder::class]);
        $this->post('/register', ['name' => 'Spanish learner', 'email' => 'spanish@example.test', 'password' => 'secure-password', 'password_confirmation' => 'secure-password'])->assertRedirect('/onboarding');
        $u = User::first();
        $this->assertEquals('spanish', $u->selected_language);
        $this->assertEquals('en', $u->translation_language);
        $this->post('/onboarding', ['language' => 'spanish', 'translation_language' => 'en', 'level' => 'A1', 'daily_word_goal' => 200])->assertRedirect('/learn');
        $this->get('/stories')->assertOk()->assertSee('La panadería de Lucía')->assertDontSee('The Little Bakery');
        $this->post('/stories/'.Story::where('language', 'spanish')->where('is_premium', false)->first()->id.'/start')->assertRedirect();
        $this->assertDatabaseHas('chapters', ['user_id' => $u->id, 'language' => 'spanish']);
    }

    public function test_translation_cache_and_provider_are_language_specific(): void
    {
        Cache::flush();
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'translated']])]);
        $t = app(LearningTranslationService::class);
        $t->translate('Hola', 'es', 'en');
        $t->translate('Hola', 'es', 'vi');
        $t->translate('Hola', 'es', 'en');
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r['langpair'] === 'es|en');
        Http::assertSent(fn ($r) => $r['langpair'] === 'es|vi');
        $this->assertEquals('Hola', $t->translate('Hola', 'es', 'es'));
        Http::assertSentCount(2);
    }

    public function test_saved_meanings_survive_target_switch_and_are_not_shared(): void
    {
        $u = User::factory()->create(['is_admin' => 0, 'translation_language' => 'en', 'selected_language' => 'spanish']);
        $w = new EncounteredWord(['user_id' => $u->id, 'language' => 'spanish', 'word' => 'pan', 'stage' => -6, 'translation' => 'my bread note', 'kanji' => '', 'reading' => '', 'base_word' => '', 'base_word_reading' => '']);
        $w->lemma = 'pan';
        $w->save();
        $other = User::factory()->create(['translation_language' => 'en']);
        $ow = $w->replicate();
        $ow->user_id = $other->id;
        $ow->translation = 'private note';
        $ow->save();
        $payload = ['language' => 'spanish', 'level' => 'A1', 'daily_word_goal' => 100];
        $this->actingAs($u)->post('/onboarding', $payload + ['translation_language' => 'vi'])->assertRedirect('/learn');
        $this->assertEquals('', $w->fresh()->translation);
        $this->assertEquals(2, $w->fresh()->stage);
        $this->assertEquals('private note', $ow->fresh()->translation);
        $this->post('/onboarding', $payload + ['translation_language' => 'en'])->assertRedirect('/learn');
        $this->assertEquals('my bread note', $w->fresh()->translation);
        $this->assertEquals(-6, $w->fresh()->stage);
        $this->postJson('/learning/translate-chapter', ['chapterId' => 1, 'target' => 'xx'])->assertUnprocessable();
    }

    public function test_spanish_free_preview_can_translate_into_english_or_french(): void
    {
        Cache::flush();
        $this->seed(SpanishStorySeeder::class);
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'A little town.']])]);
        $s = Story::where('is_premium', false)->first();
        $this->postJson('/try/'.$s->id.'/translate', ['target' => 'en'])->assertOk();
        Http::assertSent(fn ($r) => $r['langpair'] === 'es|en');
        $this->postJson('/try/'.$s->id.'/translate', ['target' => 'fr'])->assertOk();
        Http::assertSent(fn ($r) => $r['langpair'] === 'es|fr');
    }
}
