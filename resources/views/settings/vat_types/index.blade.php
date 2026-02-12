@extends('layouts.app')

@section('title', 'VAT Types')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">VAT Types</h1>
        <a href="{{ route('settings.vat-types.create') }}" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i> Add VAT Type
        </a>
    </div>

    <div class="card">
        <div class="card-header"><strong>VAT List</strong></div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th style="width:80px;">#</th>
                        <th>Name</th>
                        <th style="width:160px;">VAT %</th>
                        <th style="width:180px;" class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($vatTypes as $v)
                    <tr>
                        <td>{{ $v->id }}</td>
                        <td class="fw-bold">{{ $v->name }}</td>
                        <td>{{ number_format((float)$v->vat_percent, 2) }}%</td>
                        <td class="text-end">
                            <a href="{{ route('settings.vat-types.edit', $v) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>

                            <form method="POST"
                                  action="{{ route('settings.vat-types.destroy', $v) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this VAT type?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted p-4">No VAT types found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer">
            {{ $vatTypes->links() }}
        </div>
    </div>

</div>
@endsection
