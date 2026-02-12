@extends('layouts.app')

@section('title', 'Services')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Services</h1>

        <div>
            <a href="{{ route('services.create') }}" class="btn btn-success mr-2">
                <i class="fas fa-plus-circle mr-1"></i> Add Service
            </a>

            <button class="btn btn-primary" data-toggle="modal" data-target="#importServicesModal">
                <i class="fas fa-file-import mr-1"></i> Import
            </button>
        </div>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">
        Please fix the errors below.
    </div>
    @endif

    @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header"><strong>Service List</strong></div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th style="width:70px;">#</th>
                        <th>Name</th>
                        <th style="width:180px;">Category</th>
                        <th style="width:120px;">Price</th>
                        <th style="width:140px;">VAT</th>
                        <th style="width:120px;">Duration</th>
                        <th style="width:120px;">Waiting</th>
                        <th style="width:110px;">Gender</th>
                        <th>Comment</th>
                        <th style="width:160px;" class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($services as $s)
                    <tr>
                        <td>{{ $s->id }}</td>
                        <td class="fw-bold">{{ $s->name }}</td>
                        <td>{{ $s->category?->name ?? '—' }}</td>
                        <td>€ {{ number_format((float)$s->price, 2) }}</td>
                        <td>{{ $s->vatType?->name ?? '—' }}</td>
                        <td>{{ (int)$s->duration }} min</td>
                        <td>{{ (int)$s->waiting }} min</td>
                        <td><span class="badge bg-secondary">{{ $s->gender }}</span></td>
                        <td class="text-muted">{{ $s->comment }}</td>

                        <td class="text-end">
                            <a href="{{ route('services.edit', $s) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>

                            <form method="POST" action="{{ route('services.destroy', $s) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this service?');">
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
                        <td colspan="10" class="text-center text-muted p-4">No services found.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($services, 'links'))
        <div class="card-footer">{{ $services->links() }}</div>
        @endif
    </div>

</div>

{{-- Import Services Modal --}}
<div class="modal fade" id="importServicesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('services.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import Services</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <p class="text-muted mb-2">
                        Upload <strong>CSV / XLSX / XLS</strong> with columns (in order):
                    </p>
                    <div class="bg-light p-2 rounded mb-3">
                        <code>category,name,gender,price,vat,duration,waiting,comment</code>
                    </div>

                    <div class="mb-2">
                        <a href="{{ route('services.import.template') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download mr-1"></i> Download template
                        </a>
                    </div>

                    <div class="form-group">
                        <label>Select file</label>
                        <input type="file" name="import_file" class="form-control-file" accept=".csv,.xlsx,.xls" required>
                        @error('import_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="text-muted small">
                        Notes:
                        <ul class="mb-0">
                            <li><strong>category</strong> must match an existing service category name (case-insensitive)</li>
                            <li><strong>vat</strong> can be percent like <code>19</code> or a VAT type name (case-insensitive)</li>
                            <li><strong>gender</strong> allowed: Male/Female/Both (defaults to Both)</li>
                            <li>Duplicates are skipped by (name, category, gender)</li>
                        </ul>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-import mr-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
