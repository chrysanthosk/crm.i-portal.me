@extends('layouts.app')

@section('title', 'Settings - Configuration')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Settings / Configuration</h1>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
    </div>
  @endif

  <div class="card">
    <div class="card-header"><strong>System</strong></div>
    <div class="card-body">
      <form method="POST" action="{{ route('admin.settings.config.system.update') }}">
        @csrf
        @method('PUT')

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Header Display Name</label>
            <input class="form-control" name="header_name" value="{{ old('header_name', $system->header_name ?? config('app.name')) }}" required>
            @error('header_name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Footer Display Name</label>
            <input class="form-control" name="footer_name" value="{{ old('footer_name', $system->footer_name ?? config('app.name')) }}" required>
            @error('footer_name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>
        </div>

        <button class="btn btn-primary" type="submit">
          <i class="fas fa-save mr-1"></i> Save System Settings
        </button>
      </form>
    </div>
  </div>

</div>
@endsection
