<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', ($systemHeaderName ?? config('app.name')))</title>

    {{-- AdminLTE 3 (Bootstrap 4) + FontAwesome --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        body.dark-mode .text-muted { color: rgba(255,255,255,.75) !important; }
        body.dark-mode .card, body.dark-mode .main-footer { border-color: rgba(255,255,255,.08); }
    </style>

    @stack('styles')
</head>

<body class="hold-transition sidebar-mini {{ ($uiTheme ?? 'light') === 'dark' ? 'dark-mode' : '' }}">
<div class="wrapper">

    {{-- Navbar --}}
    <nav class="main-header navbar navbar-expand
      {{ ($uiTheme ?? 'light') === 'dark' ? 'navbar-dark navbar-gray-dark' : 'navbar-white navbar-light' }}">

        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">

            {{-- Theme toggle --}}
            <li class="nav-item mr-2">
                <form method="POST" action="{{ route('theme.toggle') }}">
                    @csrf
                    <button type="submit"
                            class="btn btn-sm {{ ($uiTheme ?? 'light') === 'dark' ? 'btn-outline-light' : 'btn-outline-secondary' }}"
                            title="Toggle theme">
                        <i class="fas {{ ($uiTheme ?? 'light') === 'dark' ? 'fa-sun' : 'fa-moon' }}"></i>
                    </button>
                </form>
            </li>

            {{-- Profile dropdown --}}
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-user"></i>
                    <span class="ml-1">{{ auth()->user()->name }}</span>
                </a>

                <div class="dropdown-menu dropdown-menu-right">
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="fas fa-id-badge mr-2"></i> Profile
                    </a>

                    <div class="dropdown-divider"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </button>
                    </form>
                </div>
            </li>

        </ul>
    </nav>

    {{-- Sidebar --}}
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-text font-weight-light">{{ $systemHeaderName ?? config('app.name') }}</span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}"
                           class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    {{-- Appointments --}}
                    @if(auth()->user()->hasPermission('appointment.manage') || auth()->user()->role === 'admin')
                    <li class="nav-item">
                        <a href="{{ route('appointments.index') }}"
                           class="nav-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <p>Appointments</p>
                        </a>
                    </li>
                    @endif

                    {{-- Services --}}
                    @if(auth()->user()->hasPermission('services.manage') || auth()->user()->role === 'admin')
                    <li class="nav-item">
                        <a href="{{ route('services.index') }}"
                           class="nav-link {{ request()->routeIs('services.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-concierge-bell"></i>
                            <p>Services</p>
                        </a>
                    </li>
                    @endif

                    {{-- Clients --}}
                    @if(auth()->user()->hasPermission('client.manage') || auth()->user()->role === 'admin')
                    <li class="nav-item">
                        <a href="{{ route('clients.index') }}"
                           class="nav-link {{ request()->routeIs('clients.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-tag"></i>
                            <p>Clients</p>
                        </a>
                    </li>
                    @endif

                    {{-- Staff --}}
                    @if(auth()->user()->hasPermission('staff.manage') || auth()->user()->role === 'admin')
                    <li class="nav-item">
                        <a href="{{ route('staff.index') }}"
                           class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-nurse"></i>
                            <p>Staff</p>
                        </a>
                    </li>
                    @endif

                    {{-- Settings --}}
                    @if(auth()->user()->role === 'admin' || auth()->user()->hasPermission('admin.access'))
                    <li class="nav-header">SETTINGS</li>

                    <li class="nav-item has-treeview {{ request()->routeIs('settings.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-cogs"></i>
                            <p>Settings<i class="right fas fa-angle-left"></i></p>
                        </a>

                        <ul class="nav nav-treeview">

                            <li class="nav-item">
                                <a href="{{ route('settings.users.index') }}"
                                   class="nav-link {{ request()->routeIs('settings.users.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Users</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="{{ route('settings.roles.index') }}"
                                   class="nav-link {{ request()->routeIs('settings.roles.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Roles</p>
                                </a>
                            </li>

                            {{-- NEW: lookups for services --}}
                            <li class="nav-item">
                                <a href="{{ route('settings.service-categories.index') }}"
                                   class="nav-link {{ request()->routeIs('settings.service-categories.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Service Categories</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="{{ route('settings.vat-types.index') }}"
                                   class="nav-link {{ request()->routeIs('settings.vat-types.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>VAT Types</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="{{ route('settings.smtp.edit') }}"
                                   class="nav-link {{ request()->routeIs('settings.smtp.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>SMTP</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="{{ route('settings.config.edit') }}"
                                   class="nav-link {{ request()->routeIs('settings.config.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Configuration</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="{{ route('settings.audit.index') }}"
                                   class="nav-link {{ request()->routeIs('settings.audit.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Audit Log</p>
                                </a>
                            </li>

                        </ul>
                    </li>
                    @endif

                </ul>
            </nav>
        </div>
    </aside>

    {{-- Content --}}
    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
                @include('partials.flash')
                @yield('content')
            </div>
        </section>
    </div>

    {{-- Footer --}}
    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">{{ now()->format('Y') }}</div>
        <strong>&copy; {{ now()->format('Y') }} {{ $systemFooterName ?? config('app.name') }}</strong>
    </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

@stack('scripts')
</body>
</html>
