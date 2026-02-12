@extends('layouts.app')

@section('title', 'Staff')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Staff</h1>
        <a href="{{ route('staff.create') }}" class="btn btn-primary">
            <i class="fas fa-user-nurse mr-2"></i> Add Staff
        </a>
    </div>

    <div class="card">
        <div class="card-header"><strong>Staff List</strong></div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th style="width:70px;">#</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Mobile</th>
                        <th style="width:120px;">DOB</th>
                        <th style="width:120px;">Calendar</th>
                        <th style="width:110px;">Color</th>
                        <th style="width:120px;">Leave</th>
                        <th style="width:90px;">Pos</th>
                        <th style="width:170px;" class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($staff as $s)
                    <tr>
                        <td>{{ $s->id }}</td>

                        <td>
                            @if($s->user)
                            <div class="fw-bold">{{ $s->user->name }}</div>
                            <div class="text-muted small text-monospace">{{ $s->user->email }}</div>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>

                        <td>
                            <span class="badge bg-secondary">{{ $s->role?->role_name ?? '—' }}</span>
                        </td>

                        <td class="text-monospace">{{ $s->mobile ?? '—' }}</td>
                        <td>{{ $s->dob ? $s->dob->format('Y-m-d') : '—' }}</td>

                        <td>
                  <span class="badge {{ $s->show_in_calendar ? 'bg-success' : 'bg-secondary' }}">
                    {{ $s->show_in_calendar ? 'Yes' : 'No' }}
                  </span>
                        </td>

                        <td>
                  <span class="badge" style="background: {{ $s->color }}; color:#fff;">
                    {{ $s->color }}
                  </span>
                        </td>

                        <td>{{ number_format((float)$s->annual_leave_days, 1) }}</td>
                        <td>{{ $s->position }}</td>

                        <td class="text-end">
                            <a href="{{ route('staff.edit', $s) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>

                            <form method="POST" action="{{ route('staff.destroy', $s) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this staff member?');">
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
                        <td colspan="10" class="text-center text-muted p-4">No staff found.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($staff, 'links'))
        <div class="card-footer">{{ $staff->links() }}</div>
        @endif
    </div>

</div>
@endsection
