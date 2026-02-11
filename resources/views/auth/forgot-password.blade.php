@extends('layouts.guest-adminlte')

@section('title', 'Forgot Password')

@section('content')
  <p class="login-box-msg">Forgot your password? We’ll email you a reset link.</p>

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

  <form method="POST" action="{{ route('password.email') }}">
    @csrf

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

    <div class="row">
      <div class="col-6">
        <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-block">Back</a>
      </div>
      <div class="col-6">
        <button type="submit" class="btn btn-primary btn-block">Send Link</button>
      </div>
    </div>
  </form>
@endsection
