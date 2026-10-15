<?php

namespace App\Services;

use App\Enums\ChapterProcessingStatusEnum;
use App\Jobs\ProcessChapter;
use App\Models\Book;
// models
use App\Models\Chapter;
// services
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportService
{
    // stores the python service container's name
    private $pythonService = '';

    public function __construct()
    {
        $this->pythonService = env('PYTHON_CONTAINER_NAME', 'linguacafe-python-service');
    }

    public function importBook($userId, $userUuid, $chunkSize, $eBookChapterSortMethod, $textProcessingMethod, $file, $bookId, $bookName, $chapterName)
    {
        DB::disableQueryLog();
        $selectedLanguage = Auth::user()->selected_language;

        // tokenize book
        $text = Http::connectTimeout(3)->timeout(60)->post($this->pythonService.':8678/tokenizer/import-book', [
            'language' => $selectedLanguage,
            'chapterSortMethod' => $eBookChapterSortMethod,
            'importFile' => $file,
            'chunkSize' => $chunkSize,
        ])->throw();

        // import chunks
        $chunks = json_decode($text);
        $this->importChunks($chunks, $userId, $userUuid, $selectedLanguage, $bookName, $bookId, $chapterName);

        return 'success';
    }

    public function importText($userId, $userUuid, $chunkSize, $textProcessingMethod, $importText, $bookId, $bookName, $chapterName)
    {
        DB::disableQueryLog();
        $selectedLanguage = Auth::user()->selected_language;

        // tokenize book
        $chunks = Http::connectTimeout(3)->timeout(60)->post($this->pythonService.':8678/tokenizer/import-text', [
            'language' => $selectedLanguage,
            'importText' => $importText,
            'chunkSize' => $chunkSize,
        ])->throw();

        // import chunks
        $chunks = json_decode($chunks);
        $this->importChunks($chunks, $userId, $userUuid, $selectedLanguage, $bookName, $bookId, $chapterName);

        return 'success';
    }

    public function importSubtitles($userId, $userUuid, $chunkSize, $textProcessingMethod, $importSubtitles, $bookId, $bookName, $chapterName)
    {
        DB::disableQueryLog();
        $selectedLanguage = Auth::user()->selected_language;

        // import subtitles
        $subtitles = Http::connectTimeout(3)->timeout(60)->post($this->pythonService.':8678/tokenizer/import-subtitles', [
            'language' => $selectedLanguage,
            'subtitles' => $importSubtitles,
            'chunkSize' => $chunkSize,
        ])->throw();

        // import chunks
        $chunks = json_decode($subtitles);
        $this->importChunks($chunks, $userId, $userUuid, $selectedLanguage, $bookName, $bookId, $chapterName, true);
    }

    /*

        Imports chunks fo raw and tokenized texts. This function
        is used by other import functions to avoid code dupication.
    */
    private function importChunks($chunks, $userId, $userUuid, $language, $bookName, $bookId, $chapterName, $isSubtitle = false)
    {
        if (! is_array($chunks) || ! $chunks || count($chunks) > 2000) {
            throw new \RuntimeException('No valid chapters were extracted.');
        }
        foreach ($chunks as $chunk) {
            if (! $isSubtitle && (! is_string($chunk) || trim($chunk) === '')) {
                throw new \RuntimeException('Invalid chapter content.');
            }
        }

        return DB::transaction(function () use ($chunks, $userId, $userUuid, $language, $bookName, $bookId, $chapterName, $isSubtitle) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            abort_unless($user->selected_language === $language, 409, 'Learning language changed. Retry import after reloading.');
            // retrieve or create book
            if ($bookId == -1) {
                $book = new Book;
                $book->user_id = $userId;
                $book->cover_image = null;
                $book->language = $language;
                $book->name = $bookName;
                $book->save();
            } else {
                $book = Book::where('user_id', $userId)
                    ->where('id', $bookId)
                    ->where('language', $language)
                    ->first();

                if (! $book) {
                    abort(404, 'Resource not found.');
                }
            }

            // import each chunk as a chapter
            foreach ($chunks as $chunkIndex => $chunk) {
                $chapterNameCalculated = count($chunks) > 1 ? $chapterName.' '.($chunkIndex + 1) : $chapterName;

                $chapter = new Chapter;
                $chapter->user_id = $userId;
                $chapter->name = $chapterNameCalculated;
                $chapter->processing_status = ChapterProcessingStatusEnum::UNPROCESSED->value;
                $chapter->read_count = 0;
                $chapter->word_count = 0;
                $chapter->book_id = $book->id;
                $chapter->language = $language;
                $chapter->unique_words = '';
                $chapter->subtitle_timestamps = '';
                $chapter->type = $isSubtitle ? 'subtitle' : 'text';
                $chapter->raw_text = $isSubtitle ? json_encode($chunk) : $chunk;
                $chapter->save();

                ProcessChapter::dispatch($userId, $userUuid, $chapter->id, $language)->afterCommit();
            }

            return true;
        });
    }

    public function getYoutubeSubtitles($url)
    {
        $subtitleList = Http::connectTimeout(3)->timeout(60)->post($this->pythonService.':8678/tokenizer/get-youtube-subtitle-list', [
            'url' => $url,
        ]);

        return json_decode($subtitleList);
    }

    public function getSubtitleFileContent($fileName)
    {
        $subtitleContent = Http::connectTimeout(3)->timeout(60)->post($this->pythonService.':8678/tokenizer/get-subtitle-file-content', [
            'fileName' => $fileName,
        ]);

        return json_decode($subtitleContent);
    }

    public function getWebsiteText($url)
    {
        $websiteText = Http::connectTimeout(3)->timeout(60)->post($this->pythonService.':8678/tokenizer/get-website-text', [
            'html' => (new SafeWebsiteService)->fetch($url),
        ])->throw();

        return json_decode($websiteText);
    }
}
