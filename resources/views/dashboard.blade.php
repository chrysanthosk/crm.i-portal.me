@extends('layouts.app')

@section('title', 'Reception Dashboard')

@section('content')
<div class="row mb-3">
    <div class="col-md-3">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['todayAppointments'] }}</h3>
                <p>Today's Appointments</p>
            </div>
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>€{{ number_format($stats['todaySales'], 2) }}</h3>
                <p>Today's Sales</p>
            </div>
            <div class="icon"><i class="fas fa-cash-register"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ $stats['totalClients'] }}</h3>
                <p>Total Clients</p>
            </div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['totalServices'] }}</h3>
                <p>Services</p>
            </div>
            <div class="icon"><i class="fas fa-spa"></i></div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-8">
        <div class="card card-primary card-outline">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Today's Appointments</strong>
                <a href="{{ route('calendar_view.index') }}" class="btn btn-sm btn-outline-primary">Open Calendar</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Client</th>
                                <th>Staff</th>
                                <th>Service</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($appointments as $appointment)
                            <tr>
                                <td>{{ optional($appointment->start_at)->format('H:i') }} - {{ optional($appointment->end_at)->format('H:i') }}</td>
                                <td>{{ $appointment->client_display_name }}</td>
                                <td>{{ $appointment->staff?->user?->name ?? '—' }}</td>
                                <td>{{ $appointment->service?->name ?? '—' }}</td>
                                <td><span class="badge badge-secondary">{{ $appointment->status ?: '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No appointments scheduled for today.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-success card-outline mb-3">
            <div class="card-header"><strong>Quick Actions</strong></div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('appointments.create') }}" class="btn btn-success mb-2"><i class="fas fa-calendar-plus mr-1"></i> Add Appointment</a>
                <a href="{{ route('pos.index') }}" class="btn btn-primary mb-2"><i class="fas fa-cash-register mr-1"></i> Open Cashier</a>
                <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary mb-2"><i class="fas fa-users mr-1"></i> Open Clients</a>
                <a href="{{ route('calendar_view.index') }}" class="btn btn-outline-info"><i class="fas fa-calendar-alt mr-1"></i> View Calendar</a>
            </div>
        </div>
        <div class="card card-info card-outline">
            <div class="card-header"><strong>Today</strong></div>
            <div class="card-body">
                <p class="mb-2"><strong>Date:</strong> {{ $today->format('l, d M Y') }}</p>
                <p class="mb-2"><strong>Products:</strong> {{ $stats['totalProducts'] }}</p>
                <p class="mb-0 text-muted">Reception dashboard v1 focuses on today’s workbench: appointments, cashier, and quick actions.</p>
            </div>
        </div>
    </div>
</div>
@endsection
