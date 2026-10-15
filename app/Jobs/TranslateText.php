<?php

namespace App\Jobs;

use App\Services\LearningTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class TranslateText implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 240;

    public int $uniqueFor = 600;

    public function __construct(public string $text, public string $source, public string $target, public string $provider)
    {
        $this->onConnection('translations')->onQueue('translations');
    }

    public function uniqueId(): string
    {
        return hash('sha256', $this->source.':'.$this->target.':'.$this->provider.':'.$this->text);
    }

    public function backoff(): array
    {
        return [75];
    }

    public function handle(LearningTranslationService $translator): void
    {
        if ($translator->providerName() !== $this->provider) {
            $this->failed();

            return;
        }
        $translator->translate($this->text, $this->source, $this->target);
    }

    public function failed(?\Throwable $exception = null): void
    {
        DB::table('translation_records')->where('content_hash', hash('sha256', $this->text))
            ->where('source', $this->source)->where('target', $this->target)->where('provider', $this->provider)
            ->update(['status' => 'failed', 'retry_at' => now()->addMinute(), 'updated_at' => now()]);
    }
}
