<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use App\Services\GoalService;
use App\Services\ImportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$qaDatabaseLock = fopen(sys_get_temp_dir().'/holatales-unit-20261014.lock', 'c');
if (! flock($qaDatabaseLock, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Disposable DB busy.');
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'holatales_unit_20261014') {
    throw new RuntimeException('Disposable DB required.');
}
config(['queue.default' => 'sync', 'broadcasting.default' => 'log']);
$app->make(Kernel::class)->call('db:seed', ['--class' => 'Database\\Seeders\\SettingsSeeder', '--force' => true]);
$user = User::factory()->create(['selected_language' => 'spanish', 'translation_language' => 'en', 'uuid' => (string) Str::uuid()]);
Auth::login($user);
(new GoalService)->createGoalsForLanguage($user->id, 'spanish');
$service = app(ImportService::class);
$result = [];
foreach (['plain-text', 'epub', 'srt'] as $format) {
    $name = 'RC live '.$format;
    try {
        if ($format === 'plain-text') {
            $service->importText($user->id, $user->uuid, 500, 'detailed', 'Ana lee un libro. Lucía visita Madrid.', -1, $name, 'Ana');
        }
        if ($format === 'epub') {
            $service->importBook($user->id, $user->uuid, 500, 'spine', 'detailed', storage_path('app/temp/rc-fixture.epub'), -1, $name, 'Ana');
        }
        if ($format === 'srt') {
            $response = Http::timeout(30)->post('http://127.0.0.1:8678/tokenizer/get-subtitle-file-content', ['fileName' => storage_path('app/temp/rc-fixture.srt')])->throw();
            $service->importSubtitles($user->id, $user->uuid, 500, 'detailed', $response->body(), -1, $name, 'Ana');
        }
        $book = Book::where('user_id', $user->id)->where('name', $name)->firstOrFail();
        $chapters = Chapter::where('book_id', $book->id)->get();
        $pass = $chapters->isNotEmpty() && $chapters->every(fn ($c) => $c->processing_status === 'processed' && $c->word_count > 0);
        $result[$format] = ['status' => $pass ? 'pass' : 'fail', 'chapters' => $chapters->map(fn ($c) => ['id' => $c->id, 'status' => $c->processing_status, 'words' => $c->word_count])->all(), 'scope' => 'live parser + synchronous processing job, no browser upload or queue transport'];
    } catch (Throwable $error) {
        $result[$format] = ['status' => 'failed', 'error_type' => get_class($error)];
    }
}
file_put_contents(base_path('qa-results/rc-live-import.json'), json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
