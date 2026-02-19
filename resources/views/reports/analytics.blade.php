@extends('layouts.app')

@section('title', 'Analytics')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0">Analytics</h1>
                <div class="text-muted small">Period: {{ $start }} — {{ $end }}</div>
            </div>
            <div class="col-sm-6">
                <form method="GET" action="{{ route('reports.analytics') }}" class="form-inline float-sm-right">
                    <div class="input-group input-group-sm mr-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text">From</span>
                        </div>
                        <input type="date" name="from" class="form-control" value="{{ request('from', $start) }}">
                    </div>

                    <div class="input-group input-group-sm mr-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text">To</span>
                        </div>
                        <input type="date" name="to" class="form-control" value="{{ request('to', $end) }}">
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-sync-alt mr-1"></i> Apply
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">

    {{-- Summary cards --}}
    <div class="row">

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase small">Total Revenue</div>
                            <div class="h3 mb-0">€ {{ number_format((float)$totalRevenue, 2) }}</div>
                        </div>
                        <div class="text-muted" style="font-size: 34px;">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase small">Total Appointments</div>
                            <div class="h3 mb-0">{{ (int)$totalAppointments }}</div>
                        </div>
                        <div class="text-muted" style="font-size: 34px;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Revenue by Day --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-line mr-1"></i> Revenue by Day
            </h3>
        </div>

        <div class="card-body p-0">
            @if(!empty($days) && count($days))
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr>
                            <th style="width: 40%;">Date</th>
                            <th>Revenue</th>
                        </tr>
                        </thead>
                        <tbody>
                        @for($i=0; $i<count($days); $i++)
                            <tr>
                                <td>{{ $days[$i] }}</td>
                                <td>€ {{ number_format((float)($revenues[$i] ?? 0), 2) }}</td>
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-3 text-muted">No sales in this period.</div>
            @endif
        </div>
    </div>

    {{-- Top Staff --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-tie mr-1"></i> Top 5 Staff by Service Revenue
            </h3>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Staff</th>
                        <th style="width: 220px;">Revenue</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($topStaff as $t)
                        @php
                            $staffLabel = trim((string)($t->name ?? ''));
                            if ($staffLabel === '') {
                                $staffLabel = 'Staff #' . (($t->staff_id ?? '') ?: '');
                            }
                        @endphp
                        <tr>
                            <td>{{ $staffLabel }}</td>
                            <td>€ {{ number_format((float)($t->revenue ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-muted p-3">No staff revenue found for this period.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
