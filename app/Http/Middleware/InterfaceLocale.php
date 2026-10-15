<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class InterfaceLocale
{
    public function handle(Request $r, Closure $next)
    {
        $locale = $r->user()?->interface_locale ?? $r->session()->get('interface_locale', 'vi');
        App::setLocale(in_array($locale, ['en', 'vi'], true) ? $locale : 'vi');

        return $next($r);
    }
}
