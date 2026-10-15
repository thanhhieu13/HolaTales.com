<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupService
{
    public function createBackup(): int
    {
        $disk = Storage::disk('backup');
        $path = $disk->path('');
        if (! is_dir($path)) {
            mkdir($path, 0700, true);
        }
        $filename = 'holatales_'.now()->format('Y_m_d_H_i_s').'_'.bin2hex(random_bytes(3)).'.sql';
        $credentials = tempnam(sys_get_temp_dir(), 'ht-db-');
        chmod($credentials, 0600);
        $config = config('database.connections.mysql');
        $quote = fn ($v) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $v).'"';
        file_put_contents($credentials, "[client]\nuser=".$quote($config['username'])."\npassword=".$quote($config['password'])."\nhost=".$quote($config['host'])."\nport=".(int) $config['port']."\n");
        $tmp = $disk->path($filename.'.tmp');
        $out = fopen($tmp, 'wb');
        try {
            $process = new Process(['mysqldump', '--defaults-extra-file='.$credentials, '--no-tablespaces', '--single-transaction', $config['database']]);
            $process->setTimeout(120);
            $process->run(function ($type, $buffer) use ($out) {
                if ($type === Process::OUT) {
                    fwrite($out, $buffer);
                }
            });
            fclose($out);
            $out = null;
            if (! $process->isSuccessful() || ! filesize($tmp)) {
                @unlink($tmp);

                return 1;
            }
            rename($tmp, $disk->path($filename));
            $files = array_values(array_filter($disk->files(), fn ($f) => preg_match('/^(holatales|linguacafe)_.*\.sql$/', $f)));
            sort($files);
            $retain = max(1, (int) env('MAX_SAVED_BACKUPS', 14));
            while (count($files) > $retain) {
                $disk->delete(array_shift($files));
            }

            return 0;
        } finally {
            if (is_resource($out)) {
                fclose($out);
            }@unlink($credentials);
            @unlink($tmp);
        }
    }
}
