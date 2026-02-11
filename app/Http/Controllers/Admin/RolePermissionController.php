<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Audit;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function sync(Request $request, Role $role)
    {
        // Admin role always has everything (we still allow storing, but not required)
        $permissionIds = $request->input('permission_ids', []);
        $permissionIds = array_values(array_filter(array_map('intval', (array)$permissionIds)));

        $role->permissions()->sync($permissionIds);

        Audit::log('admin', 'role.permissions.sync', 'role', $role->id, [
            'role_key' => $role->role_key,
            'permission_count' => count($permissionIds),
        ]);

        return redirect()->route('admin.roles.index', ['role_id' => $role->id])->with('status', 'Permissions updated.');
    }
}
