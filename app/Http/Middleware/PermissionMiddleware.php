<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Usage: ->middleware('permission:settings.smtp')
     */
    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        if (!Auth::check()) {
            abort(403);
        }

        $user = Auth::user();

        // Your User model already seems to have hasPermission() (used in blade)
        if (!method_exists($user, 'hasPermission') || !$user->hasPermission($permissionKey)) {
            abort(403);
        }

        return $next($request);
    }
}
