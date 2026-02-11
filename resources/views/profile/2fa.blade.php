@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Two-Factor Authentication (2FA)</h1>
    <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left"></i> Back
    </a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">Please fix the errors below.</div>
  @endif

  <div class="row">
    <div class="col-lg-7">

      <div class="card mb-3">
        <div class="card-header"><strong>Setup</strong></div>
        <div class="card-body">

          @if(!$user->hasTwoFactorEnabled())

            <p class="text-muted mb-3">
              Generate a QR code, scan it with Authy / Google Authenticator, then enter the 6-digit code to confirm.
            </p>

            <form method="POST" action="{{ route('profile.2fa.enable') }}" class="mb-3">
              @csrf
              <button class="btn btn-primary" type="submit">
                <i class="fas fa-qrcode"></i> Generate QR
              </button>
            </form>

            @if(!empty($qrPngDataUri))
              <div class="mb-3">
                <div class="mb-2"><strong>Scan this QR</strong></div>

                <div class="d-inline-block p-2 rounded border qr-wrap">
                  <img
                    src="{{ $qrPngDataUri }}"
                    alt="2FA QR Code"
                    style="width:220px; height:220px; object-fit:contain;"
                  >
                </div>
              </div>

              @if(!empty($secret))
                <div class="text-muted small mb-3">
                  Can’t scan? Manual key:
                  <code class="px-1">{{ $secret }}</code>
                </div>
              @endif

              <form method="POST" action="{{ route('profile.2fa.confirm') }}">
                @csrf

                <div class="row">
                  <div class="col-md-4 mb-3">
                    <label class="form-label">6-digit code</label>
                    <input
                      type="text"
                      name="code"
                      class="form-control"
                      inputmode="numeric"
                      pattern="[0-9]*"
                      maxlength="6"
                      autocomplete="one-time-code"
                      required
                    >
                    @error('code') <div class="text-danger small">{{ $message }}</div> @enderror
                  </div>
                </div>

                <button class="btn btn-success" type="submit">
                  <i class="fas fa-check"></i> Confirm & Enable
                </button>
              </form>
            @endif

          @else
            <div class="alert alert-success">
              <strong>2FA is enabled</strong>.
              <div class="text-muted small">
                Enabled since: {{ optional($user->two_factor_confirmed_at)->format('Y-m-d H:i') }}
              </div>
            </div>

            <form method="POST" action="{{ route('profile.2fa.disable') }}"
                  onsubmit="return confirm('Disable 2FA?');">
              @csrf

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Current password</label>
                  <input type="password" name="current_password" class="form-control" required>
                  @error('current_password') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
              </div>

              <button class="btn btn-outline-danger" type="submit">
                <i class="fas fa-times"></i> Disable 2FA
              </button>
            </form>

            @if(\Illuminate\Support\Facades\Route::has('profile.2fa.recovery.regenerate'))
              <hr>
              <form method="POST" action="{{ route('profile.2fa.recovery.regenerate') }}"
                    onsubmit="return confirm('Regenerate recovery codes? Old codes will stop working.');">
                @csrf
                <button class="btn btn-outline-secondary" type="submit">
                  <i class="fas fa-sync"></i> Regenerate recovery codes
                </button>
              </form>
            @endif
          @endif

        </div>
      </div>

    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Recovery codes</strong>
          @if($user->hasTwoFactorEnabled() && !empty($user->two_factor_recovery_codes))
            <button id="copyRecoveryBtn" type="button" class="btn btn-xs btn-outline-secondary">
              <i class="fas fa-copy"></i> Copy
            </button>
          @endif
        </div>

        <div class="card-body">
          @if($user->hasTwoFactorEnabled() && !empty($user->two_factor_recovery_codes))
            @php
              $codes = is_array($user->two_factor_recovery_codes) ? $user->two_factor_recovery_codes : [];
            @endphp

            <p class="text-muted">
              Save these codes somewhere safe. Each code can be used once if you lose your authenticator.
            </p>

            <div class="p-3 rounded border recovery-codes-box"
                 id="recoveryCodesBox"
                 style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
              @forelse($codes as $c)
                <div>{{ $c }}</div>
              @empty
                <div class="text-muted">No recovery codes available.</div>
              @endforelse
            </div>

          @else
            <div class="text-muted">Recovery codes will appear here after 2FA is enabled.</div>
          @endif
        </div>
      </div>
    </div>

  </div>
</div>

@if($user->hasTwoFactorEnabled() && !empty($user->two_factor_recovery_codes))
<script>
(function () {
  const btn = document.getElementById('copyRecoveryBtn');
  const box = document.getElementById('recoveryCodesBox');
  if (!btn || !box) return;

  btn.addEventListener('click', async () => {
    const text = box.innerText.trim();
    try {
      await navigator.clipboard.writeText(text);
      btn.innerHTML = '<i class="fas fa-check"></i> Copied';
      setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i> Copy', 1200);
    } catch (e) {
      alert('Copy failed. Please select and copy manually.');
    }
  });
})();
</script>
@endif

@endsection
