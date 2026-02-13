@extends('layouts.app')

@section('title', 'Product Categories')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Product Categories</h1>

        <div class="d-flex">
            <a href="{{ route('settings.product-categories.template') }}" class="btn btn-outline-secondary mr-2">
                <i class="fas fa-download mr-1"></i> Template
            </a>

            <a href="{{ route('settings.product-categories.export') }}" class="btn btn-secondary mr-2">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>

            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#importCatModal">
                <i class="fas fa-file-import mr-1"></i> Import CSV
            </button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Add Category</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('settings.product-categories.store') }}">
                @csrf
                <div class="form-group mb-2">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="{{ old('name') }}" maxlength="100" required>
                    @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Create
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Category List</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:70px;">#</th>
                            <th>Name</th>
                            <th style="width:160px;" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($categories as $c)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td class="font-weight-bold">{{ $c->name }}</td>
                            <td class="text-right">

                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-toggle="modal"
                                        data-target="#editCatModal-{{ $c->id }}">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <form method="POST" action="{{ route('settings.product-categories.destroy', $c) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this category?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>

                                <div class="modal fade" id="editCatModal-{{ $c->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Category</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>

                                            <form method="POST" action="{{ route('settings.product-categories.update', $c) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label class="form-label">Name</label>
                                                        <input class="form-control"
                                                               name="name"
                                                               value="{{ old('name', $c->name) }}"
                                                               required maxlength="100">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                    <button class="btn btn-primary">
                                                        <i class="fas fa-save mr-1"></i> Save
                                                    </button>
                                                </div>
                                            </form>

                                        </div>
                                    </div>
                                </div>

                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted p-4">No categories found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($categories, 'links'))
            <div class="card-footer">{{ $categories->links() }}</div>
        @endif
    </div>

</div>

{{-- Import Categories Modal --}}
<div class="modal fade" id="importCatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST"
              action="{{ route('settings.product-categories.import') }}"
              enctype="multipart/form-data"
              class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Import Product Categories (CSV)</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">
                <div class="text-muted small mb-2">
                    CSV must include header: <code>name</code>
                </div>
                <input type="file" name="csv" class="form-control" accept=".csv,text/csv" required>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary">
                    <i class="fas fa-file-import mr-1"></i> Import
                </button>
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

@endsection
