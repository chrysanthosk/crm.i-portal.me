<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function sync(Request $request, Role $role)
    {
        // If admin role: always get ALL permissions (keeps UI consistent with "admin has access")
        if ($role->role_key === 'admin') {
            $allIds = Permission::query()->pluck('id')->map(fn($v) => (int)$v)->all();
            $role->permissions()->sync($allIds);

            return redirect()
                ->route('settings.roles.index', ['role_id' => $role->id])
                ->with('status', 'Admin role synced to ALL permissions.');
        }

        $ids = $request->input('permissions', []);
        $ids = array_map('intval', $ids);

        $role->permissions()->sync($ids);

        return redirect()
            ->route('settings.roles.index', ['role_id' => $role->id])
            ->with('status', 'Permissions saved.');
    }
}
