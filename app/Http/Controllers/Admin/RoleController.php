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
        $permissions = Permission::query()->orderBy('permission_group')->orderBy('permission_key')->get();

        $selectedRole = null;
        $selectedRoleId = $request->query('role_id');

        if ($selectedRoleId) {
            $selectedRole = $roles->firstWhere('id', (int)$selectedRoleId);
        }

        if (!$selectedRole) {
            $selectedRole = $roles->firstWhere('role_key', 'admin') ?? $roles->first();
        }

        $selectedPermissionIds = $selectedRole
            ? $selectedRole->permissions()->pluck('permissions.id')->all()
            : [];

        return view('admin.roles.index', compact('roles', 'permissions', 'selectedRole', 'selectedPermissionIds'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role_key'  => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_\-]+$/', 'unique:roles,role_key'],
            'role_name' => ['required', 'string', 'max:50'],
            'role_desc' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::create($data);

        Audit::log('admin', 'role.create', 'role', $role->id, ['role_key' => $role->role_key]);

        return redirect()->route('admin.roles.index')->with('status', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'role_key'  => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('roles', 'role_key')->ignore($role->id)],
            'role_name' => ['required', 'string', 'max:50'],
            'role_desc' => ['nullable', 'string', 'max:255'],
        ]);

        // Protect admin key
        if ($role->role_key === 'admin') {
            $data['role_key'] = 'admin';
        }

        $role->update($data);

        Audit::log('admin', 'role.update', 'role', $role->id, ['role_key' => $role->role_key]);

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        if ($role->role_key === 'admin') {
            return back()->withErrors(['role' => 'You cannot delete the admin role.']);
        }

        Audit::log('admin', 'role.delete', 'role', $role->id, ['role_key' => $role->role_key]);

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }

    /**
     * Save permissions assigned to a role.
     * Expects permission_ids[] (IDs from permissions table).
     */
    public function updatePermissions(Request $request, Role $role)
    {
        // Admin role: full access, do not edit
        if ($role->role_key === 'admin') {
            return redirect()
                ->route('admin.roles.index', ['role_id' => $role->id])
                ->with('status', 'Admin role permissions are fixed (full access).');
        }

        $data = $request->validate([
            'permission_ids'   => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $ids = array_values(array_unique($data['permission_ids'] ?? []));

        $role->permissions()->sync($ids);

        Audit::log('admin', 'role.permissions.update', 'role', $role->id, [
            'role_key' => $role->role_key,
            'permission_ids' => $ids,
        ]);

        return redirect()
            ->route('admin.roles.index', ['role_id' => $role->id])
            ->with('status', 'Permissions updated.');
    }
}
