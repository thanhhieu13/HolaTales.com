<?php

namespace App\Models;

use App\Services\GoalService;
use App\Services\LearningClock;
// services
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Phrase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'language',
        'words',
        'reading',
        'translation',
        'lookup_count',
        'relearning',
    ];

    public function setStage($stage)
    {
        // if it's a newly saved phrase, update today's achievement
        if ($this->stage >= 0 && $stage < 0) {
            (new GoalService)->updateGoalAchievement($this->user_id, $this->language, 'learn_words', 1);
        }

        if ($this->stage >= 0 && $stage < 0 && $stage !== -7) {
            $this->relearning = true;
        }

        if ($stage >= 0) {
            $this->relearning = false;
        }

        $this->stage = $stage;
        $reviewIntervals = Setting::where('name', 'reviewIntervals')->first();
        $reviewIntervals = json_decode($reviewIntervals->value);

        // find the most optimal day for the next review
        if ($stage < 0) {
            $stageString = strval($stage);
            $possibleDates = $reviewIntervals->$stageString;
            $nextReviewIndex = 0;
            for ($i = 0; $i < count($possibleDates); $i++) {
                $data = new \stdClass;
                $data->date = LearningClock::now($this->user_id)->addDays($possibleDates[$i])->toDateString();
                $data->count = Phrase::where('user_id', $this->user_id)->where('next_review', $data->date)->count();
                $possibleDates[$i] = $data;

                if ($possibleDates[$i]->count < $possibleDates[$nextReviewIndex]->count) {
                    $nextReviewIndex = $i;
                }
            }

            $this->next_review = $possibleDates[$nextReviewIndex]->date;
            if (is_null($this->added_to_srs)) {
                $this->added_to_srs = LearningClock::now($this->user_id)->toDateString();
            }
        } else {
            $this->next_review = null;
        }
    }
}
