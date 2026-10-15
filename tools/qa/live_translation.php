<?php

$qaDatabaseLock = fopen(sys_get_temp_dir().'/holatales-unit-20261014.lock', 'c');
if (! flock($qaDatabaseLock, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Disposable DB is busy; wait for other QA to finish.');
}

use App\Models\Story;
use App\Services\ContentTranslationService;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'holatales_unit_20261014') {
    throw new RuntimeException('Disposable DB required.');
}
config(['learning.translation.background' => true, 'learning.translation.provider' => 'ollama', 'queue.default' => 'redis']);
$fixtures = [
    'pronouns' => 'Lucía visita a Ana. Ana está cansada, pero Lucía la ayuda. Después, las dos vuelven a casa.',
    'negation-time-names' => 'Ana no compró tres libros. Compró dos. A las siete y cuarto, Lucía llegó a Madrid con diez euros.',
    'idiom-paragraphs' => "Ana dice: No tires la toalla. Lucía respira hondo y sigue adelante.\n\nAl día siguiente, Ana vuelve. Las dos sonríen.",
];
$fixtures['long-segmented'] = implode("\n\n", array_map(fn ($day) => "El día $day, Ana visita a Lucía en Madrid. Lucía no compra tres libros; compra dos. A las siete y cuarto, Ana vuelve a casa. Las dos recuerdan la conversación y prometen encontrarse mañana.", range(1, 12)));
if ($filter = getenv('RC_FIXTURE_FILTER')) {
    $fixtures = array_intersect_key($fixtures, [$filter => true]);
}
$results = [];
foreach ($fixtures as $name => $text) {
    $story = Story::create(['slug' => 'rc-live-'.$name.'-'.bin2hex(random_bytes(4)), 'title' => 'RC fixture '.$name, 'language' => 'spanish', 'level' => 'A1', 'topic' => 'QA', 'summary' => 'Disposable fixture', 'body' => $text, 'glossary' => [], 'glossary_language' => 'en', 'is_premium' => false]);
    $service = app(ContentTranslationService::class);
    $start = microtime(true);
    $queued = $service->request($story, 'en');
    $requestMs = (microtime(true) - $start) * 1000;
    if ($queued['status'] !== 'queued') {
        throw new RuntimeException('Expected queue status: '.$queued['status']);
    }
    $start = microtime(true);
    $worker = new Process([PHP_BINARY, '-d', 'error_reporting=24575', 'artisan', 'queue:work', 'translations', '--once', '--queue=translations', '--timeout=240', '--tries=2'], base_path());
    $worker->setTimeout(260);
    $worker->run(function ($type, $buffer) {
        file_put_contents(base_path('qa-results/rc-live-worker-detail.log'), $buffer, FILE_APPEND);
    });
    if (! $worker->isSuccessful()) {
        throw new RuntimeException('Worker failed; see rc-live-worker-detail.log');
    }
    $workerSeconds = microtime(true) - $start;
    $start = microtime(true);
    $available = $service->request($story, 'en');
    $cachedMs = (microtime(true) - $start) * 1000;
    $results[$name] = ['original' => $text, 'queued_request_ms' => round($requestMs, 2), 'worker_seconds' => round($workerSeconds, 2), 'cached_request_ms' => round($cachedMs, 2), 'result' => $available];
    file_put_contents(base_path(getenv('RC_FIXTURE_FILTER') ? 'qa-results/rc-live-translation-long.json' : 'qa-results/rc-live-translation.json'), json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo $name.': '.$available['status'].', worker '.round($workerSeconds, 2).'s'.PHP_EOL;
}
