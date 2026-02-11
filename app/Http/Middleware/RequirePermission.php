<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissionKey)
    {
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        // Super admin shortcut
        if ($user->role === 'admin') {
            return $next($request);
        }

        if ($permissionKey === 'admin.access') {
            // default guard for admin area:
            // allow only users with explicit permission OR admin role (handled above)
            if (!$user->hasPermission('admin.access')) {
                abort(403);
            }
            return $next($request);
        }

        if (!$user->hasPermission($permissionKey)) {
            abort(403);
        }

        return $next($request);
    }
}
