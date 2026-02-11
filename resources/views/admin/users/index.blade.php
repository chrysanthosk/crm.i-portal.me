@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Users</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
      <i class="fas fa-user-plus me-2"></i> Add User
    </a>
  </div>

  @if ($errors->has('delete_user'))
    <div class="alert alert-danger">
      {{ $errors->first('delete_user') }}
    </div>
  @endif

  <div class="card">
    <div class="card-header">
      <strong>User List</strong>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th style="width:70px;">#</th>
              <th>First Name</th>
              <th>Last Name</th>
              <th>Email</th>
              <th style="width:120px;">Role</th>
              <th style="width:170px;">Created</th>
              <th style="width:170px;" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($users as $u)
              <tr>
                <td>{{ $u->id }}</td>
                <td>{{ $u->first_name ?? '—' }}</td>
                <td>{{ $u->last_name ?? '—' }}</td>
                <td>
                  <span class="text-monospace">{{ $u->email }}</span>
                </td>
                <td>
                  <span class="badge {{ $u->role === 'admin' ? 'bg-danger' : 'bg-secondary' }}">
                    {{ strtoupper($u->role) }}
                  </span>
                </td>
                <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
                <td class="text-end">
                  <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit"></i>
                  </a>

                  <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                        class="d-inline"
                        onsubmit="return confirm('Delete this user?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" {{ auth()->id() === $u->id ? 'disabled' : '' }}>
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted p-4">No users found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(method_exists($users, 'links'))
      <div class="card-footer">
        {{ $users->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
