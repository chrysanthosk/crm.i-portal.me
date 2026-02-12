<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::query()->orderBy('role_key')->get();

        // Decide which role is selected in the dropdown
        $selectedRoleId = $request->integer('role_id');
        if (!$selectedRoleId && $roles->count()) {
            $selectedRoleId = (int)$roles->first()->id;
        }

        $selectedRole = $selectedRoleId
            ? $roles->firstWhere('id', $selectedRoleId) ?? Role::find($selectedRoleId)
            : null;

        // Load all permissions once (for the editor)
        $permissions = Permission::query()
            ->orderBy('permission_group')
            ->orderBy('permission_key')
            ->get();

        // Group permissions for the blade (fixes your undefined variable issue)
        $permissionsGrouped = $permissions->groupBy(function ($p) {
            return $p->permission_group ?: 'General';
        });

        // Selected role permission IDs
        $selectedRolePermissionIds = [];
        if ($selectedRole) {
            $selectedRolePermissionIds = $selectedRole->permissions()
                ->pluck('permissions.id')
                ->map(fn ($v) => (int)$v)
                ->all();
        }

        return view('admin.roles.index', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'selectedRoleId' => $selectedRoleId,
            'permissionsGrouped' => $permissionsGrouped,
            'selectedRolePermissionIds' => $selectedRolePermissionIds,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role_key'  => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/', 'unique:roles,role_key'],
            'role_name' => ['required', 'string', 'max:120'],
        ]);

        $role = Role::create($data);

        Audit::log('settings', 'role.create', 'role', $role->id, [
            'role_key' => $role->role_key,
            'role_name' => $role->role_name,
        ]);

        return redirect()
            ->route('settings.roles.index', ['role_id' => $role->id])
            ->with('status', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'role_key'  => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('roles', 'role_key')->ignore($role->id),
            ],
            'role_name' => ['required', 'string', 'max:120'],
        ]);

        $role->update($data);

        Audit::log('settings', 'role.update', 'role', $role->id, [
            'role_key' => $role->role_key,
            'role_name' => $role->role_name,
        ]);

        return redirect()
            ->route('settings.roles.index', ['role_id' => $role->id])
            ->with('status', 'Role updated.');
    }

    public function destroy(Request $request, Role $role)
    {
        // Optional: block deleting a core/admin role if you want
        // if ($role->role_key === 'admin') {
        //     return back()->withErrors(['role_delete' => 'You cannot delete the admin role.']);
        // }

        Audit::log('settings', 'role.delete', 'role', $role->id, [
            'role_key' => $role->role_key,
            'role_name' => $role->role_name,
        ]);

        $role->delete();

        return redirect()
            ->route('settings.roles.index')
            ->with('status', 'Role deleted.');
    }
}
