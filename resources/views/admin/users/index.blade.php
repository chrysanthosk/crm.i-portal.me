@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Users</h1>
        <a href="{{ route('settings.users.create') }}" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i> Add User
        </a>
    </div>

    @if ($errors->has('delete_user'))
    <div class="alert alert-danger">
        {{ $errors->first('delete_user') }}
    </div>
    @endif

    <div class="card">
        <div class="card-header"><strong>User List</strong></div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th style="width:70px;">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th style="width:160px;">Role</th>
                        <th style="width:220px;">Created</th>
                        <th style="width:170px;" class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $u)
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td class="fw-bold">{{ $u->name }}</td>
                        <td class="text-monospace">{{ $u->email }}</td>
                        <td>
                                <span class="badge bg-secondary">
                                    {{ $u->role?->role_name ?? ($u->role ?? '—') }}
                                </span>
                        </td>
                        <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="text-end">

                            <a href="{{ route('settings.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>

                            <form method="POST" action="{{ route('settings.users.destroy', $u) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this user?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>

                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted p-4">No users found.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($users, 'links'))
        <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>

</div>
@endsection
