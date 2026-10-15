<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InterfaceLocaleController extends Controller
{
    public function store(Request $r)
    {
        $data = $r->validate(['interface_locale' => 'required|in:en,vi']);
        if ($user = $r->user()) {
            $user->interface_locale = $data['interface_locale'];
            $user->save();
        }
        $r->session()->put('interface_locale', $data['interface_locale']);

        return redirect('/onboarding')->with('status', 'Interface language saved. Learning pair unchanged.');
    }
}
