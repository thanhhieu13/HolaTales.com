<?php

namespace App\Services;

use App\Enums\ChapterProcessingStatusEnum;
use App\Events\ChapterStateUpdatedEvent;
use App\Jobs\ProcessChapter;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EncounteredWord;
use App\Models\Phrase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChapterService
{
    private $bookService;

    public function __construct()
    {
        $this->bookService = new BookService;
    }

    public function getChaptersForBook($userId, $bookId)
    {
        $book = Book::where('id', $bookId)
            ->where('user_id', $userId)
            ->first();

        if (! $book) {
            abort(404, 'Resource not found.');
        }

        $chapters = Chapter::select(['id', 'name', 'read_count', 'word_count', 'unique_word_ids', 'processing_status'])
            ->where('book_id', $bookId)
            ->where('user_id', $userId)
            ->get();

        $words = EncounteredWord::select(['id', 'word', 'stage'])
            ->where('user_id', $userId)
            ->where('language', $book->language)
            ->get()
            ->keyBy('id')
            ->toArray();

        for ($i = 0; $i < count($chapters); $i++) {
            $chapters[$i]->wordCount = new \stdClass;
            $chapters[$i]->wordCount->total = $chapters[$i]->word_count;
            $chapters[$i]->wordCount->unique = -1;
            $chapters[$i]->wordCount->known = -1;
            $chapters[$i]->wordCount->highlighted = -1;
            $chapters[$i]->wordCount->new = -1;
            if ($chapters[$i]->processing_status === ChapterProcessingStatusEnum::PROCESSED->value) {
                $chapters[$i]->wordCount = $chapters[$i]->getWordCounts($words);
            }
        }

        $data = new \stdClass;
        $data->book = $book;
        $data->chapters = $chapters;

        return $data;
    }

    public function getChaptersBookCount($userId, $userUuid, $bookId)
    {
        $book = Book::where('id', $bookId)
            ->where('user_id', $userId)
            ->first();

        if (! $book) {
            abort(404, 'Resource not found.');
        }

        $chapters = Chapter::where('book_id', $bookId)
            ->where('user_id', $userId)
            ->get();

        $words = EncounteredWord::select(['id', 'word', 'stage'])
            ->where('user_id', $userId)
            ->where('language', $book->language)
            ->get()
            ->keyBy('id')
            ->toArray();

        $chaptersWithWordCounts = [];
        for ($i = 0; $i < count($chapters); $i++) {
            if ($chapters[$i]->processing_status !== ChapterProcessingStatusEnum::PROCESSED->value) {
                continue;
            }

            $currentChapterWordCounts = new \stdClass;
            $currentChapterWordCounts->wordCount = $chapters[$i]->getWordCounts($words);

            $chaptersWithWordCounts[$chapters[$i]->id] = $currentChapterWordCounts;

            // push data on websockets in 5 item chunks
            if ($i % 5 === 0 || $i === count($chapters) - 1) {
                event(new ChapterStateUpdatedEvent($userUuid, $chaptersWithWordCounts));
                $chaptersWithWordCounts = [];
            }
        }

        return true;
    }

    public function getChapterForEditor($userId, $chapterId)
    {
        $chapter = Chapter::select(['name', 'raw_text', 'type'])
            ->where('id', $chapterId)
            ->where('user_id', $userId)
            ->first();

        if (! $chapter) {
            abort(404, 'Resource not found.');
        }

        $chapter->raw_text = str_replace(" NEWLINE \r\n", "\r\n", $chapter->raw_text);

        return $chapter;
    }

    public function getChapterForReader($userId, $language, $languagesWithoutSpaces, $chapterId)
    {
        $chapter = Chapter::where('id', $chapterId)
            ->where('user_id', $userId)
            ->where('language', $language)
            ->where('processing_status', ChapterProcessingStatusEnum::PROCESSED->value)
            ->first();

        if (! $chapter) {
            abort(404, 'Resource not found.');
        }

        $book = Book::where('id', $chapter->book_id)
            ->where('user_id', $userId)
            ->first();

        $chapters = Chapter::select(['id', 'name', 'read_count', 'word_count', 'unique_word_ids', 'processing_status'])
            ->where('user_id', $userId)
            ->where('book_id', $book->id)
            ->get();

        $words = $chapter->getProcessedText();

        // get chapter word counts
        $uniqueWordsForWordCounts = EncounteredWord::select(['id', 'word', 'stage'])
            ->where('user_id', $userId)
            ->where('language', $chapter->language)
            ->get()
            ->keyBy('id')
            ->toArray();

        for ($i = 0; $i < count($chapters); $i++) {
            $chapters[$i]->wordCount = new \stdClass;
            $chapters[$i]->wordCount->total = $chapters[$i]->word_count;
            $chapters[$i]->wordCount->unique = -1;
            $chapters[$i]->wordCount->known = -1;
            $chapters[$i]->wordCount->highlighted = -1;
            $chapters[$i]->wordCount->new = -1;

            if ($chapters[$i]->processing_status !== ChapterProcessingStatusEnum::PROCESSED->value) {
                continue;
            }

            $chapters[$i]->wordCount = $chapters[$i]->getWordCounts($uniqueWordsForWordCounts);
        }

        $textBlock = new TextBlockService($userId, $language);
        $textBlock->setProcessedWords($words);
        $textBlock->collectUniqueWords();
        $textBlock->prepareTextForReader();
        $textBlock->indexPhrases();

        $data = new \stdClass;
        $data->type = $chapter->type;
        $data->subtitleTimestamps = $chapter->subtitle_timestamps;
        $data->words = $textBlock->words;
        $data->uniqueWords = $textBlock->uniqueWords;
        $data->phrases = $textBlock->phrases;
        $data->bookName = $book->name;
        $data->chapterId = $chapter->id;
        $data->chapterName = $chapter->name;
        $data->bookId = $book->id;
        $data->language = $chapter->language;
        $data->languageSpaces = ! in_array($language, $languagesWithoutSpaces, true);
        $data->chapters = $chapters;
        $data->wordCount = $chapter->word_count;

        return $data;
    }

    public function finishChapter($userId, $chapterId, $autoMoveWordsToKnown, $uniqueWords, $autoLevelUpWords, $leveledUpWords, $leveledUpPhrases, $language)
    {
        return DB::transaction(function () use ($userId, $chapterId, $autoMoveWordsToKnown, $uniqueWords, $autoLevelUpWords, $leveledUpWords, $leveledUpPhrases, $language) {
            Chapter::where('user_id', $userId)->where('language', $language)->lockForUpdate()->findOrFail($chapterId);

            return $this->finishChapterOnce($userId, $chapterId, $autoMoveWordsToKnown, $uniqueWords, $autoLevelUpWords, $leveledUpWords, $leveledUpPhrases, $language);
        });
    }

    private function finishChapterOnce($userId, $chapterId, $autoMoveWordsToKnown, $uniqueWords, $autoLevelUpWords, $leveledUpWords, $leveledUpPhrases, $language)
    {
        $chapter = Chapter::where('user_id', $userId)->where('language', $language)->findOrFail($chapterId);
        // Preserve existing completions without counting them again.
        $inserted = DB::table('reading_completions')->insertOrIgnore(['user_id' => $userId, 'chapter_id' => $chapterId, 'language' => $language, 'word_count' => $chapter->word_count, 'created_at' => now(), 'updated_at' => now()]);
        if (! $inserted || $chapter->read_count > 0) {
            return true;
        }
        // automove words that the user sees the first time,
        // but they already know it to learned stage.
        DB::beginTransaction();
        if ($autoMoveWordsToKnown) {
            foreach ($uniqueWords as $uniqueWordData) {
                $saveData = [];
                $saveData['read_count'] = $uniqueWordData->read_count;

                if ($uniqueWordData->stage == 2) {
                    $saveData['stage'] = 0;
                }

                EncounteredWord::where('id', $uniqueWordData->id)
                    ->where('user_id', $userId)
                    ->update($saveData);
            }
        }

        DB::commit();

        // increase chapter read count
        $chapter = Chapter::where('id', $chapterId)
            ->where('user_id', $userId)
            ->first();

        if (! $chapter) {
            abort(404, 'Resource not found.');
        }

        $chapter->read_count++;
        $chapter->save();

        // updage today's reading achievement
        (new GoalService)->updateGoalAchievement($userId, $language, 'read_words', $chapter->word_count);

        // level up phrases
        if (! $autoLevelUpWords) {
            return true;
        }

        foreach ($leveledUpPhrases as $phraseId) {
            $phrase = Phrase::where('id', $phraseId)
                ->where('user_id', $userId)
                ->where('language', $language)
                ->first();

            if (! $phrase) {
                throw new \Exception('Leveled up phrase not found.');
            }

            $phrase->setStage($phrase->stage + 1);
            $phrase->save();
        }

        // level up words
        foreach ($leveledUpWords as $wordId) {
            $word = EncounteredWord::where('id', $wordId)
                ->where('user_id', $userId)
                ->where('language', $language)
                ->first();

            if (! $word) {
                throw new \Exception('Leveled up word not found.');
            }

            $word->setStage($word->stage + 1);
            $word->save();
        }

        return true;
    }

    public function createChapter($userId, $userUuid, $bookId, $chapterName, $chapterText)
    {

        // retrieve book
        $book = Book::where('id', $bookId)
            ->where('user_id', $userId)
            ->first();

        if (! $book) {
            abort(404, 'Resource not found.');
        }

        $chapter = new Chapter;
        $chapter->user_id = $userId;
        $chapter->processing_status = ChapterProcessingStatusEnum::UNPROCESSED->value;
        $chapter->name = $chapterName;
        $chapter->type = 'text';
        $chapter->subtitle_timestamps = '';
        $chapter->read_count = 0;
        $chapter->word_count = 0;
        $chapter->book_id = $bookId;
        $chapter->language = $book->language;
        $chapter->unique_words = '';
        $chapter->save();

        $this->updateChapter($userId, $userUuid, $chapter->id, $chapter->name, $chapterText);

        return true;
    }

    // updates the name and text of a chapter
    public function updateChapter($userId, $userUuid, $chapterId, $chapterName, $chapterText)
    {
        DB::disableQueryLog();

        // retrieve chapter
        $chapter = Chapter::where('id', $chapterId)
            ->where('user_id', $userId)
            ->first();

        if (! $chapter) {
            abort(404, 'Resource not found.');
        }

        // update chapter data
        $chapter->raw_text = $chapterText;
        $chapter->name = $chapterName;
        $chapter->processing_status = ChapterProcessingStatusEnum::UNPROCESSED->value;
        $chapter->save();

        ProcessChapter::dispatch($userId, $userUuid, $chapter->id, $chapter->language)->afterCommit();

        return true;
    }

    // processes a chapter's raw text, and returns the amount of words in the chapter
    public function processChapterText($userId, $chapterId)
    {
        DB::disableQueryLog();
        $bookId = null;

        DB::transaction(function () use (&$bookId, $userId, $chapterId) {
            // Match pair mutations/heartbeat lock order: user before chapter.
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            // retrieve chapter
            $chapter = Chapter::lockForUpdate()
                ->where('id', $chapterId)
                ->where('user_id', $userId)
                ->first();

            if (! $chapter) {
                abort(404, 'Resource not found.');
            }

            // process text
            $textBlock = new TextBlockService($userId, $chapter->language);

            if ($chapter->type == 'text') {
                $textBlock->rawText = $chapter->raw_text;
                $textBlock->tokenizeRawText();
                $timeStamps = [];
            } else {
                $textBlock->rawText = $chapter->raw_text;
                $timeStamps = $textBlock->tokenizeRawSubtitles();
            }

            $textBlock->processTokenizedWords();
            $textBlock->collectUniqueWords();
            $textBlock->updateAllPhraseIds();
            $textBlock->createNewEncounteredWords();

            // collect unique word ID-s
            $uniqueWordIds = DB::table('encountered_words')
                ->select('id')
                ->where('user_id', $userId)
                ->where('language', $chapter->language)
                ->whereIn('word', $textBlock->uniqueWords)
                ->pluck('id')
                ->toArray();

            // update chapter word data
            $chapter->word_count = $textBlock->getWordCount();
            $chapter->unique_words = json_encode($textBlock->uniqueWords);
            $chapter->unique_word_ids = json_encode($uniqueWordIds);
            $chapter->setProcessedText($textBlock->processedWords);
            $chapter->subtitle_timestamps = json_encode($timeStamps);
            $chapter->processing_status = ChapterProcessingStatusEnum::PROCESSED->value;
            $chapter->save();

            $bookId = $chapter->book_id;
        });

        $this->bookService->updateBookWordCount($userId, $bookId);
    }

    public function deleteChapter($userId, $chapterId)
    {

        // retrieve chapter
        $chapter = Chapter::where('user_id', $userId)
            ->where('id', $chapterId)
            ->first();

        // check if chapter is found
        if (! $chapter) {
            abort(404, 'Resource not found.');
        }

        // delete chapter
        $chapter->delete();

        // update book word counts
        $this->bookService->updateBookWordCount($userId, $chapter->book_id);

        return true;
    }

    public function retryFailedChapters($userId, $userUuid, $bookId)
    {
        return DB::transaction(function () use ($userId, $userUuid, $bookId) {
            $book = Book::where('user_id', $userId)->whereKey($bookId)->lockForUpdate()->first();
            abort_unless($book, 404, 'Resource not found.');
            $chapters = Chapter::where('user_id', $userId)->where('book_id', $bookId)->where('processing_status', ChapterProcessingStatusEnum::FAILED->value)->lockForUpdate()->get();
            foreach ($chapters as $chapter) {
                $chapter->processing_status = ChapterProcessingStatusEnum::UNPROCESSED->value;
                $chapter->save();
                ProcessChapter::dispatch($userId, $userUuid, $chapter->id, $chapter->language)->afterCommit();
            }

            return true;
        });
    }
}
