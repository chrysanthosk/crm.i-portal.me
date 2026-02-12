<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissionKey)
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized.');
        }

        $user = Auth::user();

        // Allow admins always (your hasPermission already does this, but keep safe)
        if (!method_exists($user, 'hasPermission')) {
            abort(500, 'User model is missing hasPermission().');
        }

        if (!$user->hasPermission($permissionKey)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
