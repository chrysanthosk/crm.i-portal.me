<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::query()->orderBy('id')->get();

        $selectedRoleId = (int)($request->query('role_id') ?? ($roles->first()?->id ?? 0));
        $selectedRole   = $roles->firstWhere('id', $selectedRoleId);

        $permissions = Permission::query()
            ->orderBy('permission_group')
            ->orderBy('permission_name')
            ->orderBy('permission_key')
            ->get();

        $permissionsGrouped = $permissions->groupBy(fn($p) => $p->permission_group ?: 'General');

        $selectedRolePermissionIds = [];
        if ($selectedRole) {
            $selectedRolePermissionIds = $selectedRole->permissions()
                ->pluck('permissions.id')
                ->map(fn($v) => (int)$v)   // SQLite returns strings
                ->values()
                ->all();
        }

        $view = view()->exists('settings.roles.index') ? 'settings.roles.index' : 'roles.index';

        return view($view, [
            'roles' => $roles,
            'selectedRoleId' => $selectedRoleId,
            'selectedRole' => $selectedRole,
            'permissionsGrouped' => $permissionsGrouped,
            'selectedRolePermissionIds' => $selectedRolePermissionIds,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role_name' => ['required', 'string', 'max:190'],
            'role_key'  => ['required', 'string', 'max:190', 'regex:/^[a-z0-9_-]+$/', 'unique:roles,role_key'],
        ]);

        Role::create($data);

        return redirect()->route('settings.roles.index')->with('status', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'role_name' => ['required', 'string', 'max:190'],
            'role_key'  => ['required', 'string', 'max:190', 'regex:/^[a-z0-9_-]+$/', 'unique:roles,role_key,' . $role->id],
        ]);

        $role->update($data);

        return redirect()->route('settings.roles.index', ['role_id' => $role->id])->with('status', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return redirect()->route('settings.roles.index')->with('status', 'Role deleted.');
    }
}
