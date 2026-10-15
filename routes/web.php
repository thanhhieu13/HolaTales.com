<?php

use App\Http\Controllers\AnkiController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\FontTypeController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\JellyfinController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VocabularyController;
use App\Http\Middleware\GuardLearningPair;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

require __DIR__.'/auth.php';

/*
    This function's authentication is inside the controller, because
    the first user can be created without being logged in.
*/
Route::group(['middleware' => 'web'], function () {
    Route::post('/users/create', [UserController::class, 'createUser']);
});

// login routes
Route::get('/login', [UserController::class, 'showLoginForm'])->name('login');
Route::post('/login', [UserController::class, 'authenticateUser'])->middleware('throttle:10,1');

Route::group(['middleware' => ['auth', 'auth.session', 'web']], function () {

    Route::group(['middleware' => 'admin'], function () {
        Route::get('/dev', [HomeController::class, 'index']);

        // backup
        Route::post('/backups/create', [BackupController::class, 'createBackup'])->middleware('throttle:1,1');

        // users
        Route::get('/users/get', [UserController::class, 'getUsers']);
        Route::post('/users/update', [UserController::class, 'updateUser']);

        // languages
        Route::post('/languages/install', [LanguageController::class, 'installLanguage']);
        Route::get('/languages/installed/list', [LanguageController::class, 'getInstalledLanguages']);
        Route::delete('/languages/installed/delete', [LanguageController::class, 'deleteInstalledLanguages']);
        Route::get('/languages/get-admin-language-settings-data', [LanguageController::class, 'getAdminLanguageSettingsData']);

        // dictionaries
        Route::post('/dictionary/update', [DictionaryController::class, 'updateDictionary']);

        // vue routes
        Route::get('/admin/{page?}', [HomeController::class, 'index']);

        // fonts
        Route::get('/fonts/get', [FontTypeController::class, 'getInstalledFontTypes']);
        Route::post('/fonts/upload', [FontTypeController::class, 'uploadFontType']);
        Route::post('/fonts/update', [FontTypeController::class, 'updateFontType']);
        Route::post('/fonts/delete', [FontTypeController::class, 'deleteFontType']);

        // settings
        Route::post('/settings/global/update', [SettingsController::class, 'updateGlobalSettings']);
        Route::post('/settings/global/get', [SettingsController::class, 'getGlobalSettingsByName']);

        // dictionaries
        Route::post('/dictionaries/get-supported-dictionary-file-information', [DictionaryController::class, 'getDictionaryFileInformation']);
        Route::post('/dictionaries/import', [DictionaryController::class, 'importSupportedDictionary']);
        Route::get('/dictionaries/get-record-count/{dictionaryTableName}', [DictionaryController::class, 'getDictionaryRecordCount']);
        Route::get('/dictionaries/deepl/get-usage', [DictionaryController::class, 'getDeeplCharacterLimit']);
        Route::get('/dictionaries/get', [DictionaryController::class, 'getDictionaries']);
        Route::get('/dictionaries/get/{dictionaryId}', [DictionaryController::class, 'getDictionary']);
        Route::post('/dictionaries/update', [DictionaryController::class, 'updateDictionary']);
        Route::post('/dictionaries/test-csv-file', [DictionaryController::class, 'testDictionaryCsvFile']);
        Route::post('/dictionaries/import-csv-file', [DictionaryController::class, 'importDictionaryCsvFile']);
        Route::post('/dictionaries/create-deepl', [DictionaryController::class, 'createDeeplDictionary']);
        Route::post('/dictionaries/create-my-memory', [DictionaryController::class, 'createMyMemoryDictionary']);
        Route::post('/dictionaries/create-custom-api', [DictionaryController::class, 'createCustomApiDictionary']);
        Route::post('/dictionaries/create-libre-translate', [DictionaryController::class, 'createLibreTranslateDictionary']);
        Route::get('/dictionaries/delete/{dictionaryId}', [DictionaryController::class, 'deleteDictionary']);
        Route::get('/jmdict/xml-to-text', [DictionaryController::class, 'jmdictXmlToText']);
    });

    // languages
    Route::get('/languages/get-language-selection-dialog-data', [LanguageController::class, 'getLanguageSelectionDialogData']);
    Route::get('/languages/select/{language}', [LanguageController::class, 'selectLanguage']);

    // users
    Route::post('/users/update-password', [UserController::class, 'updatePassword']);
    Route::get('/users/is-password-changed', [UserController::class, 'isUserPasswordChanged']);
    Route::delete('/users/delete-language-data/{language}', [UserController::class, 'deleteUserLanguageData']);

    // jellyfin
    Route::get('/jellyfin/subtitles', [JellyfinController::class, 'getJellyfinCurrentlyPlayedSubtitles']);

    // vue routes
    Route::get('/legacy-home', [HomeController::class, 'index']);
    Route::get('/user-settings', [HomeController::class, 'index']);
    Route::get('/user-manual/{currentPage?}', [HomeController::class, 'index']);
    Route::get('/attributions', [HomeController::class, 'index']);
    Route::get('/patch-notes', [HomeController::class, 'index']);
    Route::get('/books/{bookId?}', [HomeController::class, 'index']);
    Route::get('/book/create', [HomeController::class, 'index']);
    Route::get('/chapters/{id}', [HomeController::class, 'index']);
    Route::get('/chapters/read/{id}', [HomeController::class, 'index']);
    Route::get('/chapters/create/{bookId}', [HomeController::class, 'index']);
    Route::get('/chapters/edit/{bookId}/{chapterId}', [HomeController::class, 'index']);
    Route::get('/review/{practiceMode?}/{bookId?}/{chapterId?}', [HomeController::class, 'index']);
    Route::get('/vocabulary/search', [HomeController::class, 'index']);
    Route::get('/vocabulary/search/{text}/{stage}/{book}/{chapter}/{translation}/{phrases}/{orderBy}/{page}', [HomeController::class, 'index']);
    Route::get('/kanji/search', [HomeController::class, 'index']);
    Route::get('/kanji/{character}', [HomeController::class, 'index']);

    // home
    Route::post('/statistics/get', [HomeController::class, 'getStatistics']);
    Route::get('/config/get/{configPath}', [HomeController::class, 'getConfig']);

    // user manual
    Route::get('/manual/get-menu-tree', [HomeController::class, 'getUserManualTree']);
    Route::get('/manual/get-manual-file/{fileName}', [HomeController::class, 'getUserManualFile']);

    // goals
    Route::post('/goals/get', [GoalController::class, 'getGoals']);
    Route::post('/goal/update', [GoalController::class, 'updateGoal']);
    Route::post('/goals/get-calendar-data', [GoalController::class, 'getCalendarData']);
    Route::post('/goals/achievement/update', [GoalController::class, 'updateCalendarData']);
    Route::post('/goals/achievement/review/update', fn () => abort(409, 'Review goals are recorded by /reviews/submit.'));

    // fonts
    Route::get('/fonts/get-fonts-for-language/{language}', [FontTypeController::class, 'getFontTypesForLanguage']);
    Route::get('/fonts/file/{fileName}', [FontTypeController::class, 'getFontTypeFile']);

    // settings
    Route::post('/settings/user/get', [SettingsController::class, 'getUserSettingsByName']);
    Route::post('/settings/user/update', [SettingsController::class, 'updateUserSettings']);
    Route::get('/settings/is-jellyfin-enabled', [SettingsController::class, 'isJellyfinEnabled']);
    Route::get('/settings/get-anki-settings', [SettingsController::class, 'getAnkiSettings']);

    // images
    Route::get('/images/book_images/{fileName}', [ImageController::class, 'getBookImage']);
    Route::get('/images/kanji/{fileName}', [ImageController::class, 'getKanjiImage']);

    // dictionaries
    Route::post('/dictionaries/api/search', [DictionaryController::class, 'searchApiDictionaries']);
    Route::get('/dictionaries/api/is-enabled', [DictionaryController::class, 'isAnyApiDictionaryEnabled']);
    Route::post('/dictionaries/search', [DictionaryController::class, 'searchDefinitions']);
    Route::post('/dictionaries/search-for-hover-vocabulary', [DictionaryController::class, 'searchDefinitionsForHoverVocabulary']);
    Route::post('/dictionaries/search/inflections', [DictionaryController::class, 'searchInflections']);

    // vocabulary
    Route::get('/vocabulary/words/get/{wordId}', [VocabularyController::class, 'getUniqueWord']);
    Route::post('/vocabulary/word/update', [VocabularyController::class, 'updateWord'])->middleware(GuardLearningPair::class);
    Route::get('/vocabulary/phrases/get/{phraseId}', [VocabularyController::class, 'getPhrase']);
    Route::post('/vocabulary/phrases/create', [VocabularyController::class, 'createPhrase'])->middleware(GuardLearningPair::class);
    Route::post('/vocabulary/phrases/update', [VocabularyController::class, 'updatePhrase'])->middleware(GuardLearningPair::class);
    Route::post('/vocabulary/phrases/delete', [VocabularyController::class, 'deletePhrase'])->middleware(GuardLearningPair::class);
    Route::post('/vocabulary/example-sentence/create-or-update', [VocabularyController::class, 'createOrUpdateExampleSentence'])->middleware(GuardLearningPair::class);
    Route::post('/vocabulary/search', [VocabularyController::class, 'searchVocabulary']);
    Route::post('/vocabulary/export-to-csv', [VocabularyController::class, 'exportToCsv']);
    Route::post('/vocabulary/import-from-csv', [VocabularyController::class, 'importFromCsv'])->middleware(GuardLearningPair::class);
    Route::get('/vocabulary/example-sentence/{targetType}/{targetId}', [VocabularyController::class, 'getExampleSentence']);
    Route::post('/kanji/search', [VocabularyController::class, 'searchKanji']);
    Route::post('/kanji/details', [VocabularyController::class, 'getKanjiDetails']);

    // review
    Route::post('/reviews', [ReviewController::class, 'getReviewItems']);
    Route::post('/reviews/submit', [ReviewController::class, 'submit'])->middleware('throttle:120,1');
    Route::post('/reviews/update', fn () => abort(409, 'Review counts are recorded by /reviews/submit.'));

    // anki
    Route::post('/anki/add-card', [AnkiController::class, 'addCardToAnki']);

    // books
    Route::post('/books', [BookController::class, 'getBooks']);
    Route::get('/books/get-word-counts/{bookId}', [BookController::class, 'getBookWordCounts']);
    Route::post('/books/create', [BookController::class, 'createBook']);
    Route::post('/books/update', [BookController::class, 'updateBook']);
    Route::post('/books/delete', [BookController::class, 'deleteBook']);

    // chapters
    Route::post('/chapters', [ChapterController::class, 'getChaptersForBook']);
    Route::get('/chapters/word-counts/{bookId}', [ChapterController::class, 'getChaptersBookCount']);
    Route::post('/chapters/get/reader', [ChapterController::class, 'getChapterForReader']);
    Route::post('/chapters/get/editor', [ChapterController::class, 'getChapterForEditor']);
    Route::post('/chapters/delete', [ChapterController::class, 'deleteChapter']);
    Route::post('/chapters/finish', [ChapterController::class, 'finishChapter']);
    Route::post('/chapters/update', [ChapterController::class, 'updateChapter']);
    Route::post('/chapters/create', [ChapterController::class, 'createChapter']);
    Route::post('/chapters/retry-failed-chapters/{bookId}', [ChapterController::class, 'retryFailedChapters']);

    // library import
    Route::post('/import', [ImportController::class, 'import']);
    Route::post('/youtube/get-subtitle-list', [ImportController::class, 'getYoutubeSubtitles']);
    Route::post('/subtitle/get-subtitle-file-content', [ImportController::class, 'getSubtitleFileContent']);
    Route::post('/website/get-text', [ImportController::class, 'getWebsiteText']);
});

require __DIR__.'/learning.php';
