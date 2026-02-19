@extends('layouts.app')

@section('title', 'Clients')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Clients</h1>

        <div class="d-flex" style="gap:10px;">
            <a href="{{ route('clients.export') }}" class="btn btn-outline-secondary">
                <i class="fas fa-file-export mr-2"></i> Export
            </a>

            <a href="{{ route('clients.import.template') }}" class="btn btn-outline-secondary">
                <i class="fas fa-download mr-2"></i> Download Template
            </a>

            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#importClientsModal">
                <i class="fas fa-file-import mr-2"></i> Import
            </button>

            <a href="{{ route('clients.create') }}" class="btn btn-primary">
                <i class="fas fa-user-tag mr-2"></i> Add Client
            </a>
        </div>
    </div>

    {{-- Import errors (row-level) --}}
    @if(session('import_errors'))
        <div class="alert alert-warning">
            <div class="font-weight-bold mb-2">Some rows were skipped due to validation errors:</div>
            <ul class="mb-0">
                @foreach(session('import_errors') as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><strong>Client List</strong></div>

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
                        <td><span class="badge bg-secondary">{{ $c->gender }}</span></td>
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
        <div class="card-footer">{{ $clients->links() }}</div>
        @endif
    </div>

</div>

{{-- Import Modal --}}
<div class="modal fade" id="importClientsModal" tabindex="-1" role="dialog" aria-labelledby="importClientsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('clients.import') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="importClientsModalLabel">Import Clients (CSV)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info">
                    <div class="mb-1"><strong>Tip:</strong> Download the template and fill it in.</div>
                    <div class="small text-muted">
                        Import will <strong>update existing clients by email</strong> and create new ones.
                    </div>
                </div>

                <div class="form-group">
                    <label>CSV File</label>
                    <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                    @error('file') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="text-muted small">
                    Required columns: <code>first_name,last_name,dob,mobile,email,gender</code>.
                    Optional: <code>registration_date,address,city,notes,comments</code>.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload mr-1"></i> Import
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
