<?php

namespace App\Services;

use App\Jobs\TranslateText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LearningTranslationService
{
    public function providerName(): string
    {
        return match (config('learning.translation.provider')) {
            'ollama' => 'Ollama:'.config('learning.translation.ollama_model').':literary-v3',
            'mymemory' => 'MyMemory',
            default => throw new RuntimeException('Unsupported translation provider.'),
        };
    }

    public function isExternal(): bool
    {
        return config('learning.translation.provider') !== 'ollama';
    }

    public function request(string $text, string $source, string $target): array
    {
        if (trim($text) === '' || mb_strlen($text) > 5000
            || ! in_array($source, array_column(config('learning.sources'), 'code'), true)
            || ! array_key_exists($target, config('learning.targets'))) {
            throw new RuntimeException('Unsupported text or language pair.');
        }
        $provider = $this->providerName();
        if ($source === $target) {
            return ['status' => 'available', 'translation' => $text, 'source' => $provider];
        }
        $identity = ['content_hash' => hash('sha256', $text), 'source' => $source, 'target' => $target];

        return Cache::lock('translation-request:'.hash('sha256', json_encode($identity).$provider), 10)->block(1, function () use ($identity, $provider, $text, $source, $target) {
            $record = DB::table('translation_records')->where($identity)->first();
            if ($record && $record->provider === $provider) {
                if ($record->status === 'available') {
                    return ['status' => 'available', 'translation' => $record->translation, 'source' => $provider, 'review_status' => $record->review_status];
                }
                if ($record->status === 'failed' && $record->retry_at && now()->lt($record->retry_at)) {
                    return ['status' => 'failed', 'message' => 'Translation unavailable. Retry after one minute.'];
                }
                if (in_array($record->status, ['queued', 'processing']) && now()->diffInSeconds($record->updated_at, true) < 600) {
                    return ['status' => $record->status];
                }
            }
            if (config('queue.default') === 'sync') {
                return ['status' => 'unavailable', 'message' => 'Translation worker is not configured.'];
            }
            DB::table('translation_records')->updateOrInsert($identity, ['provider' => $provider, 'status' => 'queued', 'retry_at' => null, 'created_at' => $record?->created_at ?? now(), 'updated_at' => now()]);
            try {
                TranslateText::dispatch($text, $source, $target, $provider);
            } catch (\Throwable $error) {
                DB::table('translation_records')->where($identity)->update(['status' => 'failed', 'retry_at' => now()->addMinute()]);

                return ['status' => 'unavailable', 'message' => 'Translation queue is unavailable.'];
            }

            return ['status' => 'queued'];
        });
    }

    public function translate(string $text, string $source = 'en', string $target = 'vi'): string
    {
        $lockKey = 'translation-lock:'.hash('sha256', $source.':'.$target.':'.$this->providerName().':'.$text);

        return Cache::lock($lockKey, max(180, (int) config('learning.translation.timeout', 180) + 30))->block(5, fn () => $this->translateUnlocked($text, $source, $target));
    }

    private function translateUnlocked(string $text, string $source, string $target): string
    {
        if (trim($text) === '') {
            throw new RuntimeException('Empty text cannot be translated.');
        }
        if (mb_strlen($text) > 5000) {
            throw new RuntimeException('Đoạn dịch quá dài. Vui lòng dịch từng đoạn tối đa 5.000 ký tự.');
        }
        if (! in_array($source, array_column(config('learning.sources'), 'code'), true) || ! array_key_exists($target, config('learning.targets'))) {
            throw new RuntimeException('Unsupported language pair.');
        }
        if ($source === $target) {
            return $text;
        }
        $provider = $this->providerName();
        $key = 'learning-translation:'.$source.':'.$target.':'.hash('sha256', $text);
        if (is_array($cached = Cache::get($key)) && ($cached['provider'] ?? null) === $provider && is_string($cached['translation'] ?? null)) {
            return $cached['translation'];
        }
        $identity = ['content_hash' => hash('sha256', $text), 'source' => $source, 'target' => $target];
        $record = DB::table('translation_records')->where($identity)->first();
        if ($record && $record->status === 'available' && $record->provider === $provider) {
            return $record->translation;
        }
        if ($record && $record->provider === $provider && $record->status === 'failed' && $record->retry_at && now()->lt($record->retry_at)) {
            throw new RuntimeException('Translation temporarily unavailable.');
        }
        DB::table('translation_records')->updateOrInsert($identity, ['status' => 'processing', 'provider' => $provider, 'created_at' => $record?->created_at ?? now(), 'updated_at' => now()]);
        try {
            $translation = config('learning.translation.provider') === 'ollama'
                ? (new AiTranslationService)->translate($text, $source, $target)
                : $this->translateMyMemory($text, $source, $target);
            if ($translation !== '') {
                Cache::put($key, ['provider' => $provider, 'translation' => $translation], now()->addDays(30));
            }

            DB::table('translation_records')->where($identity)->update(['status' => 'available', 'review_status' => ($source === 'es' && $target === 'en' && stripos($text, 'no tires la toalla') !== false && stripos($translation, 'throw away the towel') !== false) ? 'needs_review' : 'unreviewed', 'translation' => $translation, 'retry_at' => null, 'updated_at' => now()]);

            return $translation;
        } catch (\Throwable $e) {
            DB::table('translation_records')->where($identity)->update(['status' => 'failed', 'retry_at' => now()->addMinute(), 'updated_at' => now()]);
            throw $e;
        }
    }

    private function translateMyMemory(string $text, string $source, string $target): string
    {
        // Split by Unicode characters so every provider query stays within 450 UTF-8 bytes.
        $chunks = [];
        $chunk = '';
        foreach (preg_split('/(?<=\s)/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (strlen($chunk.$part) > 450 && $chunk !== '') {
                $chunks[] = trim($chunk);
                $chunk = '';
            }
            if (strlen($part) > 450) {
                throw new RuntimeException('Văn bản có từ quá dài để dịch.');
            }
            $chunk .= $part;
        }
        if (trim($chunk) !== '') {
            $chunks[] = trim($chunk);
        }
        $result = [];
        foreach ($chunks as $q) {
            $r = Http::connectTimeout(3)->timeout(8)->get('https://api.mymemory.translated.net/get', ['q' => $q, 'langpair' => $source.'|'.$target]);
            $d = $r->json();
            $value = $d['responseData']['translatedText'] ?? null;
            if (! $r->successful() || (int) ($d['responseStatus'] ?? 0) !== 200 || ($d['quotaFinished'] ?? false) || ! is_string($value) || trim($value) === '') {
                throw new RuntimeException('Dịch vụ dịch đang không khả dụng hoặc đã hết hạn mức. Vui lòng thử lại sau.');
            }
            $result[] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return implode(' ', $result);
    }
}
