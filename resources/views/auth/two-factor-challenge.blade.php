@extends('layouts.guest-adminlte')

@section('title', 'Two-Factor Verification')

@section('content')
  <p class="login-box-msg">Two-Factor Verification</p>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Verification failed.</strong>
    </div>
  @endif

  <div class="mb-3 text-muted">
    Enter the 6-digit code from your authenticator app, or use a recovery code.
  </div>

  <form method="POST" action="{{ route('2fa.verify') }}">
    @csrf

    <div class="input-group mb-2">
      <input type="text" name="code" class="form-control" placeholder="123456"
             inputmode="numeric" autocomplete="one-time-code">
      <div class="input-group-append">
        <div class="input-group-text"><span class="fas fa-key"></span></div>
      </div>
    </div>
    @error('code') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

    <div class="text-center text-muted my-2">— or —</div>

    <div class="input-group mb-2">
      <input type="text" name="recovery_code" class="form-control" placeholder="RECOVERY-CODE" autocomplete="off">
      <div class="input-group-append">
        <div class="input-group-text"><span class="fas fa-life-ring"></span></div>
      </div>
    </div>
    @error('recovery_code') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

    <div class="icheck-primary mt-3 mb-2">
      <input id="remember_device" type="checkbox" name="remember_device" value="1">
      <label for="remember_device">Remember this device for 30 days</label>
    </div>

    <div class="row mt-3">
      <div class="col-6">
        <button class="btn btn-primary btn-block" type="submit">Verify</button>
      </div>
  </form>

      <div class="col-6">
        <form method="POST" action="{{ route('2fa.cancel') }}">
          @csrf
          <button class="btn btn-outline-secondary btn-block" type="submit">Cancel</button>
        </form>
      </div>
    </div>
@endsection
