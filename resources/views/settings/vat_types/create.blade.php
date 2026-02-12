@extends('layouts.app')

@section('title', 'Add VAT Type')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Add VAT Type</h1>
        <a href="{{ route('settings.vat-types.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.vat-types.store') }}">
                @csrf

                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text"
                           name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}"
                           maxlength="150"
                           required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label>VAT Percent <span class="text-danger">*</span></label>
                    <input type="number"
                           step="0.01"
                           min="0"
                           max="100"
                           name="vat_percent"
                           class="form-control @error('vat_percent') is-invalid @enderror"
                           value="{{ old('vat_percent', '0.00') }}"
                           required>
                    @error('vat_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <small class="text-muted">Example: 19.00</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i> Save
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
