<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class EnsureTheme
{
    public function handle(Request $request, Closure $next)
    {
        // ---------- THEME ----------
        $theme = 'light';

        // 1) If logged in and user has a theme, use it
        if (Auth::check() && !empty(Auth::user()->theme)) {
            $theme = Auth::user()->theme;
        }
        // 2) Else prefer cookie (persists for guest pages & after logout)
        elseif ($request->hasCookie('ui_theme')) {
            $theme = (string) $request->cookie('ui_theme');
        }
        // 3) Else fallback to session
        elseif ($request->session()->has('theme')) {
            $theme = (string) $request->session()->get('theme', 'light');
        }

        if (!in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }

        // Keep session in sync (helps after login)
        $request->session()->put('theme', $theme);

        // ---------- SYSTEM BRANDING ----------
        $headerName = config('app.name');
        $footerName = config('app.name');

        try {
            if (Schema::hasTable('settings')) {
                $system = Setting::query()->where('key', 'system')->first();
                $val = $system?->value ?? [];

                if (is_array($val)) {
                    $headerName = $val['header_name'] ?? $headerName;
                    $footerName = $val['footer_name'] ?? $footerName;
                }
            }
        } catch (\Throwable $e) {
            // ignore branding failures to avoid breaking the UI
        }

        // Share to all views
        View::share('uiTheme', $theme);
        View::share('systemHeaderName', $headerName);
        View::share('systemFooterName', $footerName);

        return $next($request);
    }
}
