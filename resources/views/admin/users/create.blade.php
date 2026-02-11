@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Create User</h1>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-2"></i> Back
    </a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      Please fix the errors below.
    </div>
  @endif

  <div class="card">
    <div class="card-header">
      <strong>User Details</strong>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}">
            @error('first_name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}">
            @error('last_name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-8 mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-control" required>
              @foreach($roles as $r)
                <option value="{{ $r->role_key }}" @selected(old('role') === $r->role_key)>
                  {{ $r->role_name }} ({{ $r->role_key }})
                </option>
              @endforeach
            </select>
            @error('role') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
            @error('password') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-2"></i> Create User
        </button>
      </form>
    </div>
  </div>

</div>
@endsection
