<?php

namespace App\Services;

use App\Models\ChapterProcessingQueueStat;
use Illuminate\Support\Carbon;

class QueueStatsService
{
    public function __construct() {}

    public function insertChapterProcessedStat($chapter, $status, $dispatchedAt, $startedAt): void
    {
        // add job to stats
        $chapterProcessingQueueStat = new ChapterProcessingQueueStat;
        $chapterProcessingQueueStat->user_id = $chapter->user_id;
        $chapterProcessingQueueStat->chapter_id = $chapter->id;
        $chapterProcessingQueueStat->book_id = $chapter->book_id;
        $chapterProcessingQueueStat->status = $status;
        $chapterProcessingQueueStat->word_count = $status === 'finished' ? $chapter->word_count : null;
        $chapterProcessingQueueStat->dispatched_at = $dispatchedAt;
        $chapterProcessingQueueStat->started_at = $startedAt;
        $chapterProcessingQueueStat->finished_at = Carbon::now();
        $chapterProcessingQueueStat->save();
    }
}
