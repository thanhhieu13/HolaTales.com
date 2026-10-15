<?php

use App\Http\Controllers\InterfaceLocaleController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\LearningTranslationController;
use App\Http\Controllers\ReadingHeartbeatController;
use App\Http\Controllers\ReadingPositionController;
use App\Http\Controllers\StoryChapterController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LearningController::class, 'landing']);
Route::get('/try/{story}', [LearningController::class, 'sample']);
Route::middleware('guest')->group(function () {
    Route::get('/register', [LearningController::class, 'registerForm'])->name('register');
    Route::post('/register', [LearningController::class, 'register'])->middleware('throttle:5,1');
});
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/interface-locale', [InterfaceLocaleController::class, 'store']);
    Route::post('/reading-heartbeat/{chapter}', [ReadingHeartbeatController::class, 'store'])->middleware('throttle:10,1,reading-heartbeat');
    Route::get('/onboarding', [LearningController::class, 'onboarding']);
    Route::post('/onboarding', [LearningController::class, 'saveOnboarding']);
    Route::get('/learn', [LearningController::class, 'dashboard']);
    Route::get('/reading-position/{chapter}', [ReadingPositionController::class, 'show']);
    Route::post('/reading-position/{chapter}', [ReadingPositionController::class, 'store'])->middleware('throttle:60,1,reading-position');
    Route::get('/progress', [LearningController::class, 'dashboard']);
    Route::get('/stories', [LearningController::class, 'library']);
    Route::post('/stories/{story}/start', [LearningController::class, 'start'])->middleware('throttle:20,1');
    Route::get('/learn/books/{book}', [LearningController::class, 'book']);
    Route::get('/plans', [LearningController::class, 'plans']);
    Route::post('/editor/stories/{story}/chapters', [StoryChapterController::class, 'create']);
    Route::post('/editor/stories/{story}/chapters/{chapter}/edit', [StoryChapterController::class, 'update']);
    Route::post('/editor/stories/{story}/chapters/{chapter}/move', [StoryChapterController::class, 'move']);
    Route::post('/editor/stories/{story}/chapters/{chapter}/translations/revoke', [StoryChapterController::class, 'revoke']);
    Route::post('/editor/stories/{story}/chapters/{chapter}/translations', [StoryChapterController::class, 'translation']);
    Route::get('/editor/stories/{story}/edit', [LearningController::class, 'editStory']);
    Route::post('/editor/stories/{story}/edit', [LearningController::class, 'updateStory']);
    Route::post('/editor/stories/{story}/translations/revoke', [LearningController::class, 'revokeStoryTranslation']);
    Route::post('/editor/stories/{story}/translations', [LearningController::class, 'saveStoryTranslation']);
    Route::get('/editor/stories', [LearningController::class, 'editor']);
    Route::post('/editor/stories', [LearningController::class, 'saveStory']);
    Route::post('/editor/stories/{story}/publish', [LearningController::class, 'publish']);
});

Route::middleware(['auth', 'auth.session', 'throttle:30,1'])->group(function () {
    Route::post('/learning/translate-chapter', [LearningTranslationController::class, 'chapter']);
    Route::post('/learning/translate-word', [LearningTranslationController::class, 'word']);
});

Route::post('/try/{story}/translate', [LearningTranslationController::class, 'sample'])->middleware('throttle:10,1,reading-heartbeat');

Route::get('/catalogue', [LearningController::class, 'catalogue']);

Route::get('/catalogue/{story}', [LearningController::class, 'storyDetail']);
