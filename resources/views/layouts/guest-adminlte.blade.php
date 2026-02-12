<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>@yield('title', $systemHeaderName ?? config('app.name'))</title>

  {{-- AdminLTE 3 (Bootstrap 4) + FontAwesome --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

  <style>
    body.dark-mode .text-muted { color: rgba(255,255,255,.75) !important; }
    .login-logo a { color: inherit; }

    /* Card + form styling for dark mode */
    body.dark-mode .card,
    body.dark-mode .card-body {
      background-color: #343a40 !important;
      color: #f8f9fa !important;
    }
    body.dark-mode .form-control {
      background-color: #2b3035 !important;
      color: #f8f9fa !important;
      border-color: rgba(255,255,255,.15) !important;
    }
    body.dark-mode .input-group-text {
      background-color: #2b3035 !important;
      color: #f8f9fa !important;
      border-color: rgba(255,255,255,.15) !important;
    }

    /* Small top utility row (keeps toggle to the right always) */
    .guest-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      margin-bottom: .5rem;
    }
    .guest-topbar .left {
      min-height: 1rem;
      line-height: 1.1;
    }
    .guest-topbar .right {
      margin-left: auto;
      display: flex;
      align-items: center;
    }
  </style>
</head>

<body class="hold-transition login-page {{ ($uiTheme ?? 'light') === 'dark' ? 'dark-mode' : '' }}">
<div class="login-box">

  <div class="login-logo">
    <a href="{{ url('/') }}">
      <b>{{ $systemHeaderName ?? config('app.name') }}</b>
    </a>
  </div>

  {{-- Top utility row (header text left, theme toggle right) --}}
  <div class="guest-topbar">
    <div class="left text-muted">
      @yield('header', '')
    </div>

    <div class="right">
      <form method="POST" action="{{ route('theme.toggle') }}" class="m-0">
        @csrf
        <button type="submit"
                class="btn btn-sm {{ ($uiTheme ?? 'light') === 'dark' ? 'btn-outline-light' : 'btn-outline-secondary' }}"
                title="Toggle theme">
          <i class="fas {{ ($uiTheme ?? 'light') === 'dark' ? 'fa-sun' : 'fa-moon' }}"></i>
        </button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      @yield('content')
    </div>
  </div>

  <div class="text-center mt-3 text-muted small">
    &copy; {{ now()->format('Y') }} {{ $systemFooterName ?? config('app.name') }}
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
