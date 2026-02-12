<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\Audit;

class ThemeController extends Controller
{
    public function toggle(Request $request)
    {
        // Detect current theme from (priority): cookie -> session -> default
        $current = $request->cookie('ui_theme')
            ?? $request->session()->get('theme', 'light');

        $next = ($current === 'dark') ? 'light' : 'dark';

        // Store in session (works for logged-in pages)
        $request->session()->put('theme', $next);

        // Persist for guests + after logout (1 year)
        $cookie = cookie(
            'ui_theme',
            $next,
            60 * 24 * 365,   // minutes
            '/',
            null,
            false,           // secure (set true if https)
            false,           // httpOnly false so it's visible in browser (not required)
            false,
            'lax'
        );

        // Store in DB for logged-in users
        if (Auth::check()) {
            $user = Auth::user();
            $user->theme = $next;
            $user->save();

            Audit::log('ui', 'theme.toggle', 'user', Auth::id(), ['theme' => $next]);
        }

        return back()->withCookie($cookie);
    }
}
