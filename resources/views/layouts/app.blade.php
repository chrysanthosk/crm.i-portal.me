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

    // Permissions
    $canAppointments = ($user && ($user->role === 'admin' || $user->hasPermission('appointment.manage')));
    $canServices     = ($user && ($user->role === 'admin' || $user->hasPermission('services.manage')));
    $canProducts     = ($user && ($user->role === 'admin' || $user->hasPermission('products.manage')));
    $canInventory    = ($user && ($user->role === 'admin' || $user->hasPermission('inventory.manage')));
    $canClients      = ($user && ($user->role === 'admin' || $user->hasPermission('client.manage')));
    $canStaff        = ($user && ($user->role === 'admin' || $user->hasPermission('staff.manage')));

    // ✅ Calendar View (view OR manage)
    $canCalendarView = ($user && ($user->role === 'admin' || $user->hasPermission('calendar_view.view') || $user->hasPermission('appointment.manage')));

    // ✅ Suppliers + Bulk SMS
    $canSuppliers    = ($user && ($user->role === 'admin' || $user->hasPermission('suppliers.manage')));
    $canBulkSms      = ($user && ($user->role === 'admin' || $user->hasPermission('bulk_sms.send')));

    // POS permission
    $canPos          = ($user && ($user->role === 'admin' || $user->hasPermission('cashier.manage')));

    $canSettings     = ($user && ($user->role === 'admin' || $user->hasPermission('admin.access')));
    $canSms          = ($user && ($user->role === 'admin' || $user->hasPermission('sms.manage')));

    // Settings sub-modules
    $canPaymentMethods = ($user && ($user->role === 'admin' || $user->hasPermission('payment_methods.manage')));
    $canLoyalty        = ($user && ($user->role === 'admin' || $user->hasPermission('loyalty.manage')));
    $canGdpr           = ($user && ($user->role === 'admin' || $user->hasPermission('gdpr.manage')));

    // Reports permissions
    $canAnalytics        = ($user && ($user->role === 'admin' || $user->hasPermission('analytics.view')));
    $canBiReports        = ($user && ($user->role === 'admin' || $user->hasPermission('reporting.view')));
    $canReportsPage      = ($user && ($user->role === 'admin' || $user->hasPermission('reports.view')));

    // ✅ Staff Performance permission
    $canStaffPerformance = ($user && ($user->role === 'admin' || $user->hasPermission('staff_reports.view')));

    $canAnyReports       = ($canAnalytics || $canBiReports || $canReportsPage || $canStaffPerformance);

    // Financial (use reporting.view for now)
    $canFinancial    = ($user && ($user->role === 'admin' || $user->hasPermission('reporting.view')));

    // Active route helpers
    $isDashboard      = request()->routeIs('dashboard');
    $isCalendarView   = request()->routeIs('calendar_view.*');

    $isAppointments   = request()->routeIs('appointments.*');
    $isServicesRoute  = request()->routeIs('services.*');
    $isProductsRoute  = request()->routeIs('products.*');
    $isInventoryRoute = request()->routeIs('inventory.*');
    $isClientsRoute   = request()->routeIs('clients.*');
    $isStaffRoute     = request()->routeIs('staff.*');

    // ✅ Suppliers + Bulk SMS active state
    $isSuppliersRoute = request()->routeIs('suppliers.*');
    $isBulkSmsRoute   = request()->routeIs('bulk_sms.*');

    // POS
    $isPosRoute      = request()->routeIs('pos.*');

    // Reports
    $isReportsRoute  = request()->routeIs('reports.*') && !request()->routeIs('reports.financial.*');
    $isStaffPerfRoute = request()->routeIs('reports.staff_performance');

    // Financial
    $isFinancialRoute = request()->routeIs('reports.financial.*');

    // Settings
    $isSettingsRoute = request()->routeIs('settings.*');
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
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                    {{-- =====================================
                         GROUP: OPERATIONS
                         ===================================== --}}
                    <li class="nav-header">OPERATIONS</li>

                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    @if($canCalendarView)
                        <li class="nav-item">
                            <a href="{{ route('calendar_view.index') }}" class="nav-link {{ request()->routeIs('calendar_view.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-calendar-alt"></i>
                                <p>Calendar</p>
                            </a>
                        </li>
                    @endif

                    @if($canAppointments)
                        <li class="nav-item">
                            <a href="{{ route('appointments.index') }}" class="nav-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-calendar-check"></i>
                                <p>Appointments</p>
                            </a>
                        </li>
                    @endif

                    @if($canClients)
                        <li class="nav-item">
                            <a href="{{ route('clients.index') }}" class="nav-link {{ request()->routeIs('clients.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-user-tag"></i>
                                <p>Clients</p>
                            </a>
                        </li>
                    @endif

                    @if($canPos)
                        <li class="nav-item has-treeview {{ request()->routeIs('pos.*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-cash-register"></i>
                                <p>Cashier / Sales<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('pos.index') }}" class="nav-link {{ request()->routeIs('pos.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i><p>New Sale</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('pos.sales.index') }}" class="nav-link {{ request()->routeIs('pos.sales.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i><p>Sales History</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    {{-- =====================================
                         GROUP: CATALOG
                         ===================================== --}}
                    @if($canServices || $canProducts || $canInventory || $canSuppliers)
                    <li class="nav-header">CATALOG</li>

                    @if($canServices)
                        <li class="nav-item">
                            <a href="{{ route('services.index') }}" class="nav-link {{ request()->routeIs('services.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-concierge-bell"></i>
                                <p>Services</p>
                            </a>
                        </li>
                        @if($canSettings)
                        <li class="nav-item">
                            <a href="{{ route('settings.service-categories.index') }}" class="nav-link {{ request()->routeIs('settings.service-categories.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i><p>Service Categories</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('settings.vat-types.index') }}" class="nav-link {{ request()->routeIs('settings.vat-types.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i><p>VAT Types</p>
                            </a>
                        </li>
                        @endif
                    @endif

                    @if($canProducts)
                        <li class="nav-item">
                            <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-boxes"></i>
                                <p>Products</p>
                            </a>
                        </li>
                        @if($canSettings)
                        <li class="nav-item">
                            <a href="{{ route('settings.product-categories.index') }}" class="nav-link {{ request()->routeIs('settings.product-categories.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i><p>Product Categories</p>
                            </a>
                        </li>
                        @endif
                    @endif

                    @if($canInventory)
                        <li class="nav-item">
                            <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-warehouse"></i>
                                <p>Inventory</p>
                            </a>
                        </li>
                    @endif

                    @if($canSuppliers)
                        <li class="nav-item">
                            <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-truck"></i>
                                <p>Suppliers</p>
                            </a>
                        </li>
                    @endif

                    @if($canSettings && $canPaymentMethods)
                        <li class="nav-item">
                            <a href="{{ route('settings.payment-methods.index') }}" class="nav-link {{ request()->routeIs('settings.payment-methods.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-credit-card"></i><p>Payment Methods</p>
                            </a>
                        </li>
                    @endif

                    @endif

                    {{-- =====================================
                         GROUP: REPORTS
                         ===================================== --}}
                    @if($canAnyReports || $canFinancial || $canStaffPerformance)
                    <li class="nav-header">REPORTS</li>

                    @if($canAnyReports)
                        <li class="nav-item has-treeview {{ request()->routeIs('reports.index', 'reports.analytics', 'reports.bi', 'reports.staff_performance') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->routeIs('reports.index', 'reports.analytics', 'reports.bi', 'reports.staff_performance') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>General Reports<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                @if($canReportsPage)
                                    <li class="nav-item">
                                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i><p>Operational Reports</p>
                                        </a>
                                    </li>
                                @endif
                                @if($canAnalytics)
                                    <li class="nav-item">
                                        <a href="{{ route('reports.analytics') }}" class="nav-link {{ request()->routeIs('reports.analytics') ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i><p>Analytics</p>
                                        </a>
                                    </li>
                                @endif
                                @if($canBiReports)
                                    <li class="nav-item">
                                        <a href="{{ route('reports.bi') }}" class="nav-link {{ request()->routeIs('reports.bi') ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i><p>BI Dashboard</p>
                                        </a>
                                    </li>
                                @endif
                                @if($canStaffPerformance)
                                    <li class="nav-item">
                                        <a href="{{ route('reports.staff_performance') }}" class="nav-link {{ request()->routeIs('reports.staff_performance') ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i><p>Staff Performance</p>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if($canFinancial)
                        <li class="nav-item has-treeview {{ request()->routeIs('reports.financial.*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->routeIs('reports.financial.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-coins"></i>
                                <p>Financial<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('reports.financial.income') }}" class="nav-link {{ request()->routeIs('reports.financial.income') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i><p>Income</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('reports.financial.expenses') }}" class="nav-link {{ request()->routeIs('reports.financial.expenses') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i><p>Expenses</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif
                    @endif

                    {{-- =====================================
                         GROUP: COMMUNICATIONS
                         ===================================== --}}
                    @if($canBulkSms || ($canSettings && $canSms))
                    <li class="nav-header">COMMUNICATIONS</li>

                    @if($canBulkSms)
                        <li class="nav-item">
                            <a href="{{ route('bulk_sms.index') }}" class="nav-link {{ request()->routeIs('bulk_sms.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-sms"></i><p>Bulk SMS</p>
                            </a>
                        </li>
                    @endif

                    @if($canSettings && $canSms)
                        <li class="nav-item">
                            <a href="{{ route('settings.sms.edit') }}" class="nav-link {{ request()->routeIs('settings.sms.edit') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-comment-dots"></i><p>SMS Settings</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('settings.sms.logs') }}" class="nav-link {{ request()->routeIs('settings.sms.logs') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-history"></i><p>SMS Logs</p>
                            </a>
                        </li>
                    @endif

                    @if($canSettings)
                        <li class="nav-item">
                            <a href="{{ route('settings.smtp.edit') }}" class="nav-link {{ request()->routeIs('settings.smtp.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-envelope"></i><p>SMTP Settings</p>
                            </a>
                        </li>
                    @endif

                    @endif

                    {{-- =====================================
                         GROUP: ADMIN / SETTINGS
                         ===================================== --}}
                    @if($canSettings || $canStaff)
                    <li class="nav-header">ADMIN & SETTINGS</li>

                    @if($canStaff)
                        <li class="nav-item">
                            <a href="{{ route('staff.index') }}" class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-user-nurse"></i><p>Staff Directory</p>
                            </a>
                        </li>
                    @endif

                    @if($canSettings)
                        <li class="nav-item">
                            <a href="{{ route('settings.users.index') }}" class="nav-link {{ request()->routeIs('settings.users.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-users"></i><p>System Users</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('settings.roles.index') }}" class="nav-link {{ request()->routeIs('settings.roles.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-user-shield"></i><p>Roles & Permissions</p>
                            </a>
                        </li>

                        @if($canLoyalty)
                            <li class="nav-item">
                                <a href="{{ route('settings.loyalty.index') }}" class="nav-link {{ request()->routeIs('settings.loyalty.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-star"></i><p>Loyalty & Rewards</p>
                                </a>
                            </li>
                        @endif

                        @if($canGdpr)
                            <li class="nav-item">
                                <a href="{{ route('settings.gdpr.index') }}" class="nav-link {{ request()->routeIs('settings.gdpr.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-user-lock"></i><p>GDPR Data Purge</p>
                                </a>
                            </li>
                        @endif

                        <li class="nav-item">
                            <a href="{{ route('settings.config.edit') }}" class="nav-link {{ request()->routeIs('settings.config.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-cogs"></i><p>System Config</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('settings.audit.index') }}" class="nav-link {{ request()->routeIs('settings.audit.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-list-alt"></i><p>Audit Log</p>
                            </a>
                        </li>
                    @endif
                    @endif

                </ul>
            </nav>
        </div>
    </aside>

    {{-- Content --}}
    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">

                {{-- Flash messages --}}
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
