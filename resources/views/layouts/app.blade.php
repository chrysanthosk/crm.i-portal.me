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

@php
    $user = auth()->user();
    $isDark = (($uiTheme ?? 'light') === 'dark');

    // Permissions (keep your existing hasPermission method)
    $canAppointments = ($user && ($user->role === 'admin' || $user->hasPermission('appointment.manage')));
    $canServices     = ($user && ($user->role === 'admin' || $user->hasPermission('services.manage')));
    $canProducts     = ($user && ($user->role === 'admin' || $user->hasPermission('products.manage')));
    $canClients      = ($user && ($user->role === 'admin' || $user->hasPermission('client.manage')));
    $canStaff        = ($user && ($user->role === 'admin' || $user->hasPermission('staff.manage')));

    $canSettings     = ($user && ($user->role === 'admin' || $user->hasPermission('admin.access')));
    $canSms          = ($user && ($user->role === 'admin' || $user->hasPermission('sms.manage')));

    // Active route helpers
    $isDashboard     = request()->routeIs('dashboard');
    $isAppointments  = request()->routeIs('appointments.*');
    $isServicesRoute = request()->routeIs('services.*');
    $isProductsRoute = request()->routeIs('products.*');
    $isClientsRoute  = request()->routeIs('clients.*');
    $isStaffRoute    = request()->routeIs('staff.*');

    $isSettingsRoute = request()->routeIs('settings.*');
    $isSmsRoute      = request()->routeIs('settings.sms.*');
@endphp

<body class="hold-transition sidebar-mini {{ $isDark ? 'dark-mode' : '' }}">
<div class="wrapper">

    {{-- Navbar --}}
    <nav class="main-header navbar navbar-expand {{ $isDark ? 'navbar-dark navbar-gray-dark' : 'navbar-white navbar-light' }}">

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
                            class="btn btn-sm {{ $isDark ? 'btn-outline-light' : 'btn-outline-secondary' }}"
                            title="Toggle theme">
                        <i class="fas {{ $isDark ? 'fa-sun' : 'fa-moon' }}"></i>
                    </button>
                </form>
            </li>

            {{-- Profile dropdown --}}
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-user"></i>
                    <span class="ml-1">{{ $user?->name }}</span>
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
                           class="nav-link {{ $isDashboard ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    {{-- Appointments --}}
                    @if($canAppointments)
                    <li class="nav-item">
                        <a href="{{ route('appointments.index') }}"
                           class="nav-link {{ $isAppointments ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <p>Appointments</p>
                        </a>
                    </li>
                    @endif

                    {{-- Services --}}
                    @if($canServices)
                    <li class="nav-item">
                        <a href="{{ route('services.index') }}"
                           class="nav-link {{ $isServicesRoute ? 'active' : '' }}">
                            <i class="nav-icon fas fa-concierge-bell"></i>
                            <p>Services</p>
                        </a>
                    </li>
                    @endif

                    {{-- Products --}}
                    @if($canProducts)
                    <li class="nav-item">
                        <a href="{{ route('products.index') }}"
                           class="nav-link {{ $isProductsRoute ? 'active' : '' }}">
                            <i class="nav-icon fas fa-boxes"></i>
                            <p>Products</p>
                        </a>
                    </li>
                    @endif

                    {{-- Clients --}}
                    @if($canClients)
                    <li class="nav-item">
                        <a href="{{ route('clients.index') }}"
                           class="nav-link {{ $isClientsRoute ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-tag"></i>
                            <p>Clients</p>
                        </a>
                    </li>
                    @endif

                    {{-- Staff --}}
                    @if($canStaff)
                    <li class="nav-item">
                        <a href="{{ route('staff.index') }}"
                           class="nav-link {{ $isStaffRoute ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-nurse"></i>
                            <p>Staff</p>
                        </a>
                    </li>
                    @endif

                    {{-- Settings --}}
                    @if($canSettings)
                    <li class="nav-header">SETTINGS</li>

                    <li class="nav-item has-treeview {{ $isSettingsRoute ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ $isSettingsRoute ? 'active' : '' }}">
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

                            @if($canProducts)
                            <li class="nav-item">
                                <a href="{{ route('settings.product-categories.index') }}"
                                   class="nav-link {{ request()->routeIs('settings.product-categories.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Product Categories</p>
                                </a>
                            </li>
                            @endif

                            {{-- ✅ SMS Settings + Logs --}}
                            @if($canSms)
                            <li class="nav-item">
                                <a href="{{ route('settings.sms.edit') }}"
                                   class="nav-link {{ request()->routeIs('settings.sms.edit') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>SMS Settings</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('settings.sms.logs') }}"
                                   class="nav-link {{ request()->routeIs('settings.sms.logs') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>SMS Logs</p>
                                </a>
                            </li>
                            @endif

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

                {{-- Flash messages (no partials) --}}
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">Please fix the errors below.</div>
                @endif

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
