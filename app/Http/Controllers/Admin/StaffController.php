<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index()
    {
        $staff = Staff::query()->with(['user', 'role'])->orderBy('position')->orderByDesc('id')->paginate(20);
        return view('admin.staff.index', compact('staff'));
    }

    public function create()
    {
        $roles = Role::query()->orderBy('role_key')->get();
        $users = User::query()->orderBy('name')->get();

        return view('admin.staff.create', compact('roles', 'users'));
    }

    public function store(Request $request)
    {
        $roleIds = Role::query()->pluck('id')->all();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mobile'  => ['nullable', 'string', 'max:20'],
            'dob'     => ['nullable', 'date'],
            'role_id' => ['required', 'integer', Rule::in($roleIds)],
            'color'   => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'show_in_calendar' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
            'annual_leave_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
        ]);

        $data['show_in_calendar'] = (bool)($request->boolean('show_in_calendar'));
        $data['position'] = (int)($data['position'] ?? 0);
        $data['annual_leave_days'] = (float)($data['annual_leave_days'] ?? 0);

        $row = Staff::create($data);

        Audit::log('admin', 'staff.create', 'staff', $row->id, [
            'user_id' => $row->user_id,
            'role_id' => $row->role_id,
        ]);

        return redirect()->route('admin.staff.index')->with('status', 'Staff member created.');
    }

    public function edit(Staff $staffMember)
    {
        $staffMember->load(['user', 'role']);

        $roles = Role::query()->orderBy('role_key')->get();
        $users = User::query()->orderBy('name')->get();

        return view('admin.staff.edit', [
            'staffMember' => $staffMember,
            'roles' => $roles,
            'users' => $users,
        ]);
    }

    public function update(Request $request, Staff $staffMember)
    {
        $roleIds = Role::query()->pluck('id')->all();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mobile'  => ['nullable', 'string', 'max:20'],
            'dob'     => ['nullable', 'date'],
            'role_id' => ['required', 'integer', Rule::in($roleIds)],
            'color'   => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'show_in_calendar' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
            'annual_leave_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
        ]);

        $data['show_in_calendar'] = (bool)($request->boolean('show_in_calendar'));
        $data['position'] = (int)($data['position'] ?? 0);
        $data['annual_leave_days'] = (float)($data['annual_leave_days'] ?? 0);

        $staffMember->update($data);

        Audit::log('admin', 'staff.update', 'staff', $staffMember->id, [
            'user_id' => $staffMember->user_id,
            'role_id' => $staffMember->role_id,
        ]);

        return redirect()->route('admin.staff.index')->with('status', 'Staff member updated.');
    }

    public function destroy(Request $request, Staff $staffMember)
    {
        Audit::log('admin', 'staff.delete', 'staff', $staffMember->id, [
            'user_id' => $staffMember->user_id,
            'role_id' => $staffMember->role_id,
        ]);

        $staffMember->delete();

        return redirect()->route('admin.staff.index')->with('status', 'Staff member deleted.');
    }
}
