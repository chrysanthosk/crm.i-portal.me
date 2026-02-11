<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ThemeController extends Controller
{
    public function toggle(Request $request)
    {
        // Determine current theme from: user -> session -> cookie -> default
        $current = 'light';

        if (Auth::check() && Auth::user()?->theme) {
            $current = Auth::user()->theme;
        } elseif ($request->session()->has('theme')) {
            $current = (string)$request->session()->get('theme', 'light');
        } elseif ($request->cookie('ui_theme')) {
            $current = (string)$request->cookie('ui_theme');
        }

        $current = $current === 'dark' ? 'dark' : 'light';
        $next = $current === 'dark' ? 'light' : 'dark';

        // Store in session for immediate use
        $request->session()->put('theme', $next);

        // Store in DB for logged-in users
        if (Auth::check()) {
            $user = Auth::user();
            $user->theme = $next;
            $user->save();

            Audit::log('ui', 'theme.toggle', 'user', $user->id, ['theme' => $next]);
        }

        // ALSO store in long-lived cookie for guests + after logout
        $cookie = cookie(
            'ui_theme',
            $next,
            60 * 24 * 365,          // 365 days
            '/',
            null,
            $request->isSecure(),   // secure only on https
            true,                   // httpOnly
            false,
            'Lax'
        );

        return back()->withCookie($cookie);
    }
}
