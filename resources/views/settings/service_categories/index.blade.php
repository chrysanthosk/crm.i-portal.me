@extends('layouts.app')

@section('title', 'Service Categories')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Service Categories</h1>
        <a href="{{ route('settings.service-categories.create') }}" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i> Add Category
        </a>
    </div>

    <div class="card">
        <div class="card-header"><strong>Category List</strong></div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th style="width:80px;">#</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th style="width:180px;" class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($categories as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td class="fw-bold">{{ $c->name }}</td>
                        <td class="text-muted">{{ \Illuminate\Support\Str::limit((string)$c->description, 80) }}</td>
                        <td class="text-end">
                            <a href="{{ route('settings.service-categories.edit', $c) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>

                            <form method="POST"
                                  action="{{ route('settings.service-categories.destroy', $c) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this category?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted p-4">No categories found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer">
            {{ $categories->links() }}
        </div>
    </div>

</div>
@endsection
