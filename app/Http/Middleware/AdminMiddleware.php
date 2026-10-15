<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check() || ! Auth::user()->is_admin) {
            // If the user is not logged in or not an admin, redirect or handle as necessary
            return response()->json(['error' => 'You do not have permission to access this resource.'], 403);
        }

        return $next($request);
    }
}
