<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permissionKey)
    {
        $user = Auth::user();

        if (!$user) {
            abort(403);
        }

        // Admin role bypass
        if (($user->role ?? null) === 'admin') {
            return $next($request);
        }

        // Requires your User model to have hasPermission($key)
        if (method_exists($user, 'hasPermission') && $user->hasPermission($permissionKey)) {
            return $next($request);
        }

        abort(403);
    }
}
