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
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'       => ['required', 'string', 'max:20', Rule::in($roleKeys)],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        if ($name === '') {
            $name = $data['email'];
        }

        $user = User::create([
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'name'       => $name,
            'email'      => $data['email'],
            'role'       => $data['role'],
            'password'   => Hash::make($data['password']),
            'theme'      => 'light',
        ]);

        Audit::log('admin', 'user.create', 'user', $user->id, ['email' => $user->email, 'role' => $user->role]);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
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
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'       => ['required', 'string', 'max:20', Rule::in($roleKeys)],
            'password'   => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        if ($name === '') {
            $name = $data['email'];
        }

        $user->first_name = $data['first_name'] ?? null;
        $user->last_name  = $data['last_name'] ?? null;
        $user->name       = $name;
        $user->email      = $data['email'];
        $user->role       = $data['role'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        Audit::log('admin', 'user.update', 'user', $user->id, ['email' => $user->email, 'role' => $user->role]);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['delete_user' => 'You cannot delete your own account.']);
        }

        Audit::log('admin', 'user.delete', 'user', $user->id, ['email' => $user->email]);

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }
}
