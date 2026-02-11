@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Profile</h1>
    <a href="{{ route('profile.2fa.show') }}" class="btn btn-outline-secondary">
      <i class="fas fa-shield-alt me-2"></i> 2FA
    </a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      Please fix the errors below.
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header">
      <strong>Profile Information</strong>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}">
            @error('first_name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}">
            @error('last_name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-8 mb-3">
            <label class="form-label">Current Email</label>
            <input type="email" class="form-control" value="{{ $user->email }}" disabled>
            <div class="text-muted small mt-1">
              To change your email, use the email change request below (confirmation required).
            </div>
          </div>
        </div>

        <button class="btn btn-primary" type="submit">
          <i class="fas fa-save me-2"></i> Save Profile
        </button>
      </form>
    </div>
  </div>

  {{-- Email change request --}}
  <div class="card mb-3">
    <div class="card-header">
      <strong>Change Email</strong>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('profile.email.request') }}">
        @csrf

        <div class="row align-items-end">
          <div class="col-md-8 mb-3">
            <label class="form-label">New Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
            <div class="text-muted small mt-1">
              We’ll send a confirmation link to the new email address (using your SMTP settings).
            </div>
          </div>

          <div class="col-md-4 mb-3">
            <button class="btn btn-outline-secondary w-100" type="submit">
              <i class="fas fa-envelope me-2"></i> Send Confirmation
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Password change --}}
  <div class="card">
    <div class="card-header">
      <strong>Change Password</strong>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('profile.password') }}">
        @csrf
        @method('PATCH')

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
            @error('current_password') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">New Password</label>
            <input type="password" id="newPassword" name="password" class="form-control" required autocomplete="new-password">
            @error('password') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label d-block">Strength</label>
            <div class="progress" style="height: 8px;">
              <div id="pwStrengthBar" class="progress-bar" role="progressbar" style="width: 0%;"></div>
            </div>
            <div id="pwStrengthHint" class="text-muted small mt-2">Use 12+ chars with numbers & symbols.</div>
          </div>
        </div>

        <button class="btn btn-primary" type="submit">
          <i class="fas fa-key me-2"></i> Update Password
        </button>
      </form>
    </div>
  </div>

</div>

<script>
(function () {
  const input = document.getElementById('newPassword');
  const bar = document.getElementById('pwStrengthBar');
  const hint = document.getElementById('pwStrengthHint');
  if (!input || !bar || !hint) return;

  function score(pw) {
    let s = 0;
    if (!pw) return 0;
    if (pw.length >= 8) s++;
    if (pw.length >= 12) s++;
    if (/[A-Z]/.test(pw)) s++;
    if (/[0-9]/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return Math.min(s, 5);
  }

  function update() {
    const s = score(input.value);
    const pct = (s / 5) * 100;
    bar.style.width = pct + '%';
    bar.className = 'progress-bar';

    if (s <= 1) { bar.classList.add('bg-danger'); hint.textContent = 'Weak: add length, numbers, symbols.'; }
    else if (s === 2) { bar.classList.add('bg-warning'); hint.textContent = 'Fair: add symbols & more length.'; }
    else if (s === 3) { bar.classList.add('bg-info'); hint.textContent = 'Good: add more length or symbols.'; }
    else { bar.classList.add('bg-success'); hint.textContent = 'Strong password.'; }
  }

  input.addEventListener('input', update);
  update();
})();
</script>
@endsection
