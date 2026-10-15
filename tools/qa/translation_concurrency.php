<?php

use App\Services\LearningTranslationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'holatales_unit_20261014') {
    throw new RuntimeException('Disposable unit database only.');
}
$text = 'Ana dice: No tires la toalla. Lucía respira hondo y sigue adelante.';
$translator = app(LearningTranslationService::class);
if (($argv[1] ?? '') === 'request') {
    echo json_encode($translator->request($text, 'es', 'en'));
    exit;
}
$qaLock = fopen(sys_get_temp_dir().'/holatales-unit-20261014.lock', 'c');
if (! flock($qaLock, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('QA database busy.');
}
if (config('learning.translation.provider') !== 'ollama') {
    throw new RuntimeException('Local Ollama only.');
}
$base = rtrim(config('learning.translation.ollama_url'), '/');
Http::timeout(20)->post($base.'/api/generate', ['model' => config('learning.translation.ollama_model'), 'keep_alive' => 0])->throw();
$coldModels = Http::timeout(5)->get($base.'/api/ps')->throw()->json('models');
if (count($coldModels) !== 0) {
    throw new RuntimeException('Cannot certify a cold start while a model is loaded.');
}
$identity = ['content_hash' => hash('sha256', $text), 'source' => 'es', 'target' => 'en'];
if (DB::table('translation_records')->where($identity)->where('provider', $translator->providerName())->exists()) {
    throw new RuntimeException('Fixture already cached. Start with a fresh test DB, not a destructive cache clear.');
}
$requests = [];
for ($i = 0; $i < 2; $i++) {
    $p = new Process([PHP_BINARY, '-d', 'error_reporting=24575', __FILE__, 'request'], base_path());
    $p->setTimeout(30);
    $p->start();
    $requests[] = $p;
}
foreach ($requests as $p) {
    $p->wait();
    if (! $p->isSuccessful()) {
        throw new RuntimeException('Request process failed.');
    }
}
$queued = Queue::connection('translations')->size('translations');
if ($queued !== 1) {
    throw new RuntimeException('Duplicate or missing queue dispatch: '.$queued);
}
$start = microtime(true);
$workers = [];
for ($i = 0; $i < 2; $i++) {
    $p = new Process([PHP_BINARY, '-d', 'error_reporting=24575', 'artisan', 'queue:work', 'translations', '--queue=translations', '--stop-when-empty', '--timeout=240', '--tries=2', '-v'], base_path());
    $p->setTimeout(260);
    $p->start();
    $workers[] = $p;
}
foreach ($workers as $i => $p) {
    $p->wait();
    file_put_contents(base_path('qa-results/rc2-concurrent-worker-'.$i.'.log'), $p->getOutput().$p->getErrorOutput());
    if (! $p->isSuccessful()) {
        throw new RuntimeException('Worker failed.');
    }
}
$coldSeconds = microtime(true) - $start;
$start = microtime(true);
$result = $translator->request($text, 'es', 'en');
$cachedMs = (microtime(true) - $start) * 1000;
$artifact = ['cold_models_before' => $coldModels, 'concurrent_requests' => array_map(fn ($p) => json_decode($p->getOutput(), true), $requests), 'queued_jobs' => $queued, 'workers' => 2, 'cold_worker_wall_seconds' => round($coldSeconds, 2), 'cached_request_ms' => round($cachedMs, 2), 'original' => $text, 'result' => $result];
file_put_contents(base_path('qa-results/rc2-ai-cold-concurrency.json'), json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($artifact,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
