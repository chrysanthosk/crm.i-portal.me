@extends('layouts.app')

@section('title', 'Clients')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Clients</h1>
        <a href="{{ route('clients.create') }}" class="btn btn-primary">
            <i class="fas fa-user-tag me-2"></i> Add Client
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Client List</strong>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th style="width:70px;">#</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th style="width:130px;">DOB</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th style="width:120px;">Gender</th>
                        <th style="width:170px;">Registered</th>
                        <th style="width:170px;" class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($clients as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td>{{ $c->first_name }}</td>
                        <td>{{ $c->last_name }}</td>
                        <td>{{ optional($c->dob)->format('Y-m-d') }}</td>
                        <td class="text-monospace">{{ $c->mobile }}</td>
                        <td class="text-monospace">{{ $c->email }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ $c->gender }}</span>
                        </td>
                        <td>{{ optional($c->registration_date)->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ route('clients.edit', $c) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>

                            <form method="POST" action="{{ route('clients.destroy', $c) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this client?');">
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
                        <td colspan="9" class="text-center text-muted p-4">No clients found.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($clients, 'links'))
        <div class="card-footer">
            {{ $clients->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
