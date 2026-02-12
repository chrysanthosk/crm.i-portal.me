<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Support\Audit;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function sync(Request $request, Role $role)
    {
        $permissionIds = $request->input('permissions', []);
        if (!is_array($permissionIds)) {
            $permissionIds = [];
        }

        // Keep only valid permission IDs
        $validIds = Permission::query()
            ->whereIn('id', $permissionIds)
            ->pluck('id')
            ->map(fn ($v) => (int)$v)
            ->all();

        $role->permissions()->sync($validIds);

        Audit::log('settings', 'role.permissions.sync', 'role', $role->id, [
            'role_key' => $role->role_key,
            'permissions_count' => count($validIds),
        ]);

        return redirect()
            ->route('settings.roles.index', ['role_id' => $role->id])
            ->with('status', 'Permissions updated.');
    }
}
