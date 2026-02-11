<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        // ---------------------------
        // Theme resolution (cookie > session > user > light)
        // ---------------------------
        $cookieTheme = $request->cookie('ui_theme');
        $sessionTheme = $request->session()->get('theme');

        $theme = 'light';

        if (in_array($cookieTheme, ['light', 'dark'], true)) {
            $theme = $cookieTheme;
        } elseif (in_array($sessionTheme, ['light', 'dark'], true)) {
            $theme = $sessionTheme;
        } elseif (Auth::check()) {
            $userTheme = Auth::user()->theme ?? null;
            if (in_array($userTheme, ['light', 'dark'], true)) {
                $theme = $userTheme;
            }
        }

        // Store in session for current browsing session
        $request->session()->put('theme', $theme);

        // ---------------------------
        // System settings (DB)
        // ---------------------------
        $headerName = config('app.name', 'Laravel');
        $footerName = config('app.name', 'Laravel');

        try {
            // Only query if table exists (useful during fresh setups)
            if (Schema::hasTable('settings')) {
                $system = Setting::query()->where('key', 'system')->first();
                $raw = $system?->value;

                // Your "value" column is a JSON string like:
                // {"header_name":"medSkin","footer_name":"medSkin"}
                $systemValue = [];

                if (is_array($raw)) {
                    $systemValue = $raw;
                } elseif (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $systemValue = $decoded;
                    }
                }

                $headerName = $systemValue['header_name'] ?? $headerName;
                $footerName = $systemValue['footer_name'] ?? $footerName;
            }
        } catch (\Throwable $e) {
            // Ignore DB errors here; middleware must not break login/guest pages
        }

        // ---------------------------
        // Share to all views
        // ---------------------------
        View::share('uiTheme', $theme);
        View::share('systemHeaderName', $headerName);
        View::share('systemFooterName', $footerName);

        // ---------------------------
        // ALSO set runtime config (fixes emails saying "Laravel")
        // ---------------------------
        Config::set('app.name', $headerName);
        Config::set('mail.from.name', $headerName);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Persist theme as cookie so login page stays dark after logout/login
        // (1 year)
        $response->headers->setCookie(
            cookie('ui_theme', $theme, 60 * 24 * 365)
        );

        return $response;
    }
}
