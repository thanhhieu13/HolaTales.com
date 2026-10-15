<?php

namespace App\Services;

use App\Models\EncounteredWord;
use App\Models\Phrase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FlashcardReviewService
{
    public function submit(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            // Serialize reviews with onboarding changes so a stale tab cannot change another pair.
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->selected_language === $data['source'] && $user->translation_language === $data['target'], 409, 'Language pair changed. Reload the review queue.');
            $old = DB::table('flashcard_reviews')->where('user_id', $user->id)->where('request_id', $data['request_id'])->first();
            if ($old) {
                abort_unless($old->item_id === (int) $data['item_id'] && $old->kind === $data['kind'] && $old->grade === $data['grade'] && $old->source === $data['source'] && $old->target === $data['target'], 409, 'Request ID already used.');

                return ['stage' => $old->stage, 'relearning' => (bool) $old->relearning, 'next_review' => $old->next_review];
            }
            $model = $data['kind'] === 'word' ? EncounteredWord::class : Phrase::class;
            $item = $model::where('user_id', $user->id)->where('language', $data['source'])->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            abort_unless((int) $item->stage === (int) $data['expected_stage'] && (bool) $item->relearning === (bool) $data['expected_relearning'], 409, 'This card has changed. Reload the review queue.');
            abort_unless($item->stage < 0 && ($item->relearning || ($item->next_review && $item->next_review <= now($user->learning_timezone)->toDateString())), 409, 'This card is not due.');
            $previous = $item->stage;
            if ($data['grade'] === 'again') {
                if (! $item->relearning && $item->stage > -7) {
                    $relearning = $item->stage > -6;
                    $item->setStage($item->stage - 1);
                    $item->relearning = $relearning;
                }
            } else {
                if ($item->relearning) {
                    $item->relearning = false;
                } else {
                    $increment = ['hard' => 0, 'good' => 1, 'easy' => 2][$data['grade']];
                    $item->setStage(min(0, $item->stage + $increment));
                }
                (new GoalService)->updateGoalAchievement($user->id, $data['source'], 'review', 1);
            }
            $item->save();
            $result = ['stage' => $item->stage, 'relearning' => (bool) $item->relearning, 'next_review' => $item->next_review];
            DB::table('flashcard_reviews')->insert([
                'user_id' => $user->id, 'request_id' => $data['request_id'], 'kind' => $data['kind'],
                'item_id' => $item->id, 'source' => $data['source'], 'target' => $data['target'],
                'grade' => $data['grade'], 'previous_stage' => $previous, ...$result,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $result;
        });
    }
}
