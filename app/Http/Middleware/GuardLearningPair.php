<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GuardLearningPair
{
    public function handle(Request $request, Closure $next)
    {
        return DB::transaction(function () use ($request, $next) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($request->header('X-Learning-Source') === $user->selected_language
                && $request->header('X-Learning-Target') === $user->translation_language,
                409, 'Language pair changed or missing. Reload this page before saving.');
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);

            return $next($request);
        });
    }
}
