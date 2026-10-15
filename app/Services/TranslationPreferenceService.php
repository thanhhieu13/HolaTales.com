<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TranslationPreferenceService
{
    // Called inside the onboarding transaction; keep personal meanings when changing target.
    public function change(User $user, string $target): void
    {
        $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        $user->translation_language = $locked->translation_language;
        if ($target === $user->translation_language) {
            return;
        }
        foreach (['word' => 'encountered_words', 'phrase' => 'phrases'] as $kind => $table) {
            foreach (DB::table($table)->where('user_id', $user->id)->get(['id', 'translation', 'stage', 'next_review', 'added_to_srs', 'relearning']) as $item) {
                DB::table('saved_meanings')->updateOrInsert(['user_id' => $user->id, 'kind' => $kind, 'item_id' => $item->id, 'target' => $user->translation_language], ['translation' => $item->translation, 'stage' => $item->stage, 'next_review' => $item->next_review, 'added_to_srs' => $item->added_to_srs, 'relearning' => $item->relearning]);
            }
            DB::table($table)->where('user_id', $user->id)->update(['translation' => '', 'stage' => 2, 'next_review' => null, 'added_to_srs' => null, 'relearning' => false]);
            foreach (DB::table('saved_meanings')->where('user_id', $user->id)->where('kind', $kind)->where('target', $target)->get() as $meaning) {
                DB::table($table)->where('user_id', $user->id)->where('id', $meaning->item_id)->update(['translation' => $meaning->translation, 'stage' => $meaning->stage ?? 2, 'next_review' => $meaning->next_review, 'added_to_srs' => $meaning->added_to_srs, 'relearning' => $meaning->relearning]);
            }
        }
    }
}
