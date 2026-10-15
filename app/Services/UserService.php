<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\EncounteredWord;
use App\Models\ExampleSentence;
use App\Models\GoalAchievement;
use App\Models\Phrase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function __construct() {}

    public function getUsers($userId)
    {
        $users = User::select(['id', 'name', 'email', 'is_admin', 'password_changed', 'created_at'])
            ->get();

        foreach ($users as $user) {
            $user->created_at_text = Carbon::parse($user->created_at)->format('Y-m-d');
            $user->is_current_user = $user->id === $userId;
        }

        return $users;
    }

    public function updatePassword($user, $password)
    {
        $user->password = Hash::make($password);
        $user->password_changed = true;
        $user->save();
    }

    public function createUser($name, $email, $password, $isAdmin, $passwordChanged)
    {
        // check for duplicated e-email address
        $user = User::where('email', $email)
            ->first();

        if ($user) {
            throw new \Exception('An other already exists with this email address.');
        }

        // create user
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->is_admin = $isAdmin;
        $user->role = $isAdmin ? 'admin' : 'learner';
        $user->password_changed = $passwordChanged;
        $user->selected_language = config('learning.default_source');
        $user->translation_language = config('learning.default_target');
        $user->uuid = Str::uuid()->toString();
        $user->password = Hash::make($password);
        $user->save();

        (new GoalService)->createGoalsForLanguage($user->id, 'spanish');

        return $user;
    }

    public function updateUser($userId, $name, $email, $isAdmin)
    {
        // check for duplicated e-email address
        $user = User::where('email', $email)
            ->where('id', '<>', $userId)
            ->first();

        if ($user) {
            throw new \Exception('An other user already exists with this email address.');
        }

        // check if user can be set to not admin
        if (! $isAdmin) {
            $otherAdminAccounts = User::where('id', '<>', $userId)
                ->where('is_admin', true)
                ->count();

            if ($otherAdminAccounts === 0) {
                throw new \Exception('You cannot remove admin rights from the last admin user.');
            }
        }

        // retrieve user
        $user = User::where('id', $userId)
            ->first();

        if (! $user) {
            throw new \Exception('This user does not exist.');
        }

        // update user
        $user->name = $name;
        $user->email = $email;
        $user->is_admin = $isAdmin;
        $user->role = $isAdmin ? 'admin' : 'learner';
        $user->save();

        return true;
    }

    public function deleteUserLanguageData($userId, $language): void
    {
        DB::transaction(function () use ($userId, $language) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            foreach (['word' => EncounteredWord::class, 'phrase' => Phrase::class] as $kind => $model) {
                $ids = $model::where('user_id', $userId)->where('language', $language)->pluck('id');
                DB::table('saved_meanings')->where('user_id', $userId)->where('kind', $kind)->whereIn('item_id', $ids)->delete();
            }
            DB::table('reading_completions')->where('user_id', $userId)->where('language', $language)->delete();
            DB::table('flashcard_reviews')->where('user_id', $userId)->where('source', $language)->delete();
            $source = config('learning.sources.'.$language.'.code');
            if ($source) {
                foreach (Chapter::where('user_id', $userId)->where('language', $language)->get(['raw_text']) as $chapter) {
                    $hash = hash('sha256', $chapter->raw_text);
                    $records = DB::table('translation_records')->where('content_hash', $hash)->where('source', $source)->get(['target']);
                    foreach ($records as $record) {
                        Cache::forget('learning-translation:'.$source.':'.$record->target.':'.$hash);
                    }
                    DB::table('translation_records')->where('content_hash', $hash)->where('source', $source)->delete();
                }
            }
            Phrase::query()
                ->where('user_id', $userId)
                ->where('language', $language)
                ->delete();

            EncounteredWord::query()
                ->where('user_id', $userId)
                ->where('language', $language)
                ->delete();

            ExampleSentence::query()
                ->where('user_id', $userId)
                ->where('language', $language)
                ->delete();

            Chapter::query()
                ->where('user_id', $userId)
                ->where('language', $language)
                ->delete();

            Book::query()
                ->where('user_id', $userId)
                ->where('language', $language)
                ->delete();

            GoalAchievement::query()
                ->where('user_id', $userId)
                ->where('language', $language)
                ->delete();
        });
    }
}
