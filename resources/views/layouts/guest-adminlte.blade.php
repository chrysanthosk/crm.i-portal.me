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

    /* FORCE the card header to be a flex row (AdminLTE/Bootstrap can override otherwise) */
    .card-header.guest-card-header {
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      padding: .5rem .75rem !important;
    }

    .guest-card-header .header-left {
      min-height: 1rem;
      line-height: 1.1;
    }

    /* Optional: make card header look better in dark mode */
    body.dark-mode .card-header.guest-card-header {
      background-color: #343a40 !important;
      border-bottom-color: rgba(255,255,255,.1) !important;
    }

    body.dark-mode .card {
      background-color: #343a40;
      color: #f8f9fa;
    }

    body.dark-mode .card-body {
      background-color: #343a40;
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

  <div class="card">
    <div class="card-header guest-card-header">
      <div class="header-left text-muted">
        @yield('header', '')
      </div>

      {{-- Theme toggle (works for guests too via cookie) --}}
      <form method="POST" action="{{ route('theme.toggle') }}" class="m-0 ml-auto">
        @csrf
        <button type="submit"
                class="btn btn-sm {{ ($uiTheme ?? 'light') === 'dark' ? 'btn-outline-light' : 'btn-outline-secondary' }}"
                title="Toggle theme">
          <i class="fas {{ ($uiTheme ?? 'light') === 'dark' ? 'fa-sun' : 'fa-moon' }}"></i>
        </button>
      </form>
    </div>

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
