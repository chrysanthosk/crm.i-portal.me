@extends('layouts.guest-adminlte')

@section('title', 'Login')

@section('content')
  <p class="login-box-msg">Sign in to start your session</p>

  @if (session('status'))
    <div class="alert alert-success">
      {{ session('status') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
    </div>
  @endif

  <form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="input-group mb-3">
      <input
        type="email"
        name="email"
        class="form-control"
        placeholder="Email"
        value="{{ old('email') }}"
        required
        autofocus
        autocomplete="username"
      >
      <div class="input-group-append">
        <div class="input-group-text">
          <span class="fas fa-envelope"></span>
        </div>
      </div>
    </div>
    @error('email') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

    <div class="input-group mb-3">
      <input
        type="password"
        name="password"
        class="form-control"
        placeholder="Password"
        required
        autocomplete="current-password"
      >
      <div class="input-group-append">
        <div class="input-group-text">
          <span class="fas fa-lock"></span>
        </div>
      </div>
    </div>
    @error('password') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

    <div class="row mb-3">
      <div class="col-8">
        <div class="icheck-primary">
          <input id="remember_me" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
          <label for="remember_me">Remember Me</label>
        </div>
      </div>
      <div class="col-4">
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
      </div>
    </div>

    <p class="mb-1">
      @if (Route::has('password.request'))
        <a href="{{ route('password.request') }}">I forgot my password</a>
      @endif
    </p>
  </form>
@endsection
