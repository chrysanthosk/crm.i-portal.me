<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()->orderByDesc('id')->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::query()->orderBy('role_key')->get();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $roleKeys = Role::query()->pluck('role_key')->all();

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:190', 'unique:users,email'],
            'role'       => ['required', 'string', Rule::in($roleKeys)],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        $user = User::create([
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'name'       => $fullName !== '' ? $fullName : ($data['email'] ?? 'User'),
            'email'      => $data['email'],
            'role'       => $data['role'],
            'password'   => Hash::make($data['password']),
            'theme'      => 'light',
        ]);

        Audit::log('settings', 'user.create', 'user', $user->id, [
            'email' => $user->email,
            'role'  => $user->role,
        ]);

        return redirect()->route('settings.users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        $roles = Role::query()->orderBy('role_key')->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $roleKeys = Role::query()->pluck('role_key')->all();

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role'       => ['required', 'string', Rule::in($roleKeys)],
            'password'   => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        $update = [
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'name'       => $fullName !== '' ? $fullName : ($data['email'] ?? $user->name),
            'email'      => $data['email'],
            'role'       => $data['role'],
        ];

        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $user->update($update);

        Audit::log('settings', 'user.update', 'user', $user->id, [
            'email' => $user->email,
            'role'  => $user->role,
        ]);

        return redirect()->route('settings.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        // Optional: prevent deleting yourself
        // if ((int)auth()->id() === (int)$user->id) {
        //     return back()->withErrors(['delete_user' => 'You cannot delete your own account.']);
        // }

        Audit::log('settings', 'user.delete', 'user', $user->id, [
            'email' => $user->email,
            'role'  => $user->role,
        ]);

        $user->delete();

        return redirect()->route('settings.users.index')->with('status', 'User deleted.');
    }
}
