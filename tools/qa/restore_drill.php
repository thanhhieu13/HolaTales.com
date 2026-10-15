<?php

$qaDatabaseLock = fopen(sys_get_temp_dir().'/holatales-unit-20261014.lock', 'c');
if (! flock($qaDatabaseLock, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Disposable DB is busy; wait for other QA to finish.');
}

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

// Repeatable drill. Never read or restore the configured application database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = getenv('RC_DRILL_SOURCE') ?: 'holatales_unit_20261014';
$target = getenv('RC_DRILL_TARGET') ?: 'holatales_restore_20261014';
if (! in_array($source, ['holatales_unit_20261014', 'holatales_qa_20261014'], true) || ! preg_match('/^holatales_restore_[a-z0-9_]+$/', $target)) {
    throw new RuntimeException('Only named disposable QA sources and a new restore target are allowed.');
}
$config = config('database.connections.mysql');
$pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = $pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$target'")->fetchColumn();
if ($exists) {
    throw new RuntimeException('Restore target already exists. Choose a new disposable target in the script; existing data is never overwritten.');
}
$pdo->exec("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$credentials = tempnam(sys_get_temp_dir(), 'ht-drill-');
chmod($credentials, 0600);
$quote = fn ($v) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $v).'"';
file_put_contents($credentials, "[client]\nuser=".$quote($config['username'])."\npassword=".$quote($config['password'])."\nhost=".$quote($config['host'])."\nport=".(int) $config['port']."\n");
try {
    $dump = new Process(['mysqldump', '--defaults-extra-file='.$credentials, '--no-tablespaces', '--single-transaction', $source]);
    $dump->setTimeout(120);
    $dump->mustRun();
    $restore = new Process(['mysql', '--defaults-extra-file='.$credentials, $target]);
    $restore->setInput($dump->getOutput());
    $restore->setTimeout(120);
    $restore->mustRun();
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$source' AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $counts = [];
    foreach ($tables as $table) {
        $original = (int) $pdo->query("SELECT COUNT(*) FROM `$source`.`$table`")->fetchColumn();
        $restored = (int) $pdo->query("SELECT COUNT(*) FROM `$target`.`$table`")->fetchColumn();
        if ($original !== $restored) {
            throw new RuntimeException('Row count mismatch: '.$table);
        }
        $counts[$table] = $restored;
    }
    $keys = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='$target' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($keys as $key) {
        $sql = "SELECT COUNT(*) FROM `$target`.`{$key['TABLE_NAME']}` c LEFT JOIN `$target`.`{$key['REFERENCED_TABLE_NAME']}` p ON c.`{$key['COLUMN_NAME']}`=p.`{$key['REFERENCED_COLUMN_NAME']}` WHERE c.`{$key['COLUMN_NAME']}` IS NOT NULL AND p.`{$key['REFERENCED_COLUMN_NAME']}` IS NULL";
        if ($pdo->query($sql)->fetchColumn() > 0) {
            throw new RuntimeException('Orphan FK: '.$key['TABLE_NAME']);
        }
    }
    file_put_contents(__DIR__.'/../../qa-results/'.$target.'.json', json_encode(['date' => date(DATE_ATOM), 'source' => $source, 'target' => $target, 'counts' => $counts, 'foreign_keys_checked' => count($keys), 'status' => 'pass', 'retained_for_inspection' => true], JSON_PRETTY_PRINT));
    echo 'PASS: '.count($tables).' table counts, '.count($keys).' foreign keys; disposable restore retained.'.PHP_EOL;
} finally {
    unlink($credentials);
}
