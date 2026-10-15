<?php

namespace App\Jobs;

use App\Models\Chapter;
use App\Models\Story;
use App\Services\CatalogueService;
use App\Services\ContentTranslationService;
use App\Services\LearningTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class TranslateContent implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 240;

    public int $uniqueFor = 600;

    public function __construct(public string $kind, public int $contentId, public string $target, public string $hash, public string $provider)
    {
        $this->onConnection('translations');
        $this->onQueue('translations');
    }

    public function uniqueId(): string
    {
        return hash('sha256', implode(':', [$this->kind, $this->contentId, $this->target, $this->hash, $this->provider]));
    }

    public function backoff(): array
    {
        return [75];
    }

    public function handle(ContentTranslationService $translator): void
    {
        $content = ($this->kind === 'story' ? Story::class : Chapter::class)::find($this->contentId);
        if ($content instanceof Story) {
            $content = app(CatalogueService::class)->publicStory($content);
        }
        if (! $content || hash('sha256', $this->kind === 'story' ? $content->body : $content->raw_text) !== $this->hash
            || app(LearningTranslationService::class)->providerName() !== $this->provider) {
            $this->failed();

            return;
        }
        $translator->translate($content, $this->target);
    }

    public function failed(?\Throwable $exception = null): void
    {
        DB::table($this->kind.'_translations')->where($this->kind.'_id', $this->contentId)
            ->where('target', $this->target)->where('source_hash', $this->hash)->where('provider', $this->provider)
            ->where('review_status', '!=', 'approved')->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
