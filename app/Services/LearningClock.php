<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class LearningClock
{
    public static function now(int $userId): Carbon
    {
        return now(User::whereKey($userId)->value('learning_timezone') ?? 'UTC');
    }
}
