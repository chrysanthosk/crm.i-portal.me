@extends('layouts.guest-adminlte')

@section('title', 'Reset Password')

@section('content')
  <p class="login-box-msg">Set a new password</p>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
    </div>
  @endif

  <form method="POST" action="{{ route('password.store') }}">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div class="input-group mb-3">
      <input type="email"
             name="email"
             class="form-control"
             placeholder="Email"
             value="{{ old('email') }}"
             required
             autocomplete="email"
             autofocus>
      <div class="input-group-append">
        <div class="input-group-text">
          <span class="fas fa-envelope"></span>
        </div>
      </div>
    </div>
    @error('email') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

    <div class="input-group mb-3">
      <input type="password"
             name="password"
             class="form-control"
             placeholder="New password"
             required
             autocomplete="new-password">
      <div class="input-group-append">
        <div class="input-group-text">
          <span class="fas fa-lock"></span>
        </div>
      </div>
    </div>
    @error('password') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

    <div class="input-group mb-3">
      <input type="password"
             name="password_confirmation"
             class="form-control"
             placeholder="Confirm new password"
             required
             autocomplete="new-password">
      <div class="input-group-append">
        <div class="input-group-text">
          <span class="fas fa-lock"></span>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Reset Password</button>

    <p class="mt-3 mb-0 text-center">
      <a href="{{ route('login') }}">Back to login</a>
    </p>
  </form>
@endsection
