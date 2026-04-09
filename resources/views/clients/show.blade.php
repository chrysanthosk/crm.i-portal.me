@extends('layouts.app')

@section('title', $client->full_name . ' — Profile')

@section('content')
<div class="container-fluid">

    {{-- Page header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-user-circle mr-2 text-secondary"></i>
            {{ $client->full_name }}
        </h1>
        <div class="d-flex" style="gap:8px;">
            @if(Auth::user() && (Auth::user()->role === 'admin' || Auth::user()->hasPermission('appointment.manage')))
                <a href="{{ route('appointments.create') }}?client_id={{ $client->id }}"
                   class="btn btn-outline-success btn-sm">
                    <i class="fas fa-calendar-plus mr-1"></i> New Appointment
                </a>
            @endif
            <a href="{{ route('clients.edit', $client) }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-edit mr-1"></i> Edit
            </a>
            <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    {{-- ── STATS ROW ─────────────────────────────────────────────── --}}
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3">
            <div class="card h-100 border-left-primary">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Visits</div>
                    <div class="h4 mb-0 font-weight-bold">{{ $totalVisits }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card h-100 border-left-success">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Spent</div>
                    <div class="h4 mb-0 font-weight-bold">€{{ number_format($totalSpent, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card h-100 border-left-info">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Last Visit</div>
                    <div class="h4 mb-0 font-weight-bold">
                        {{ $lastVisit ? $lastVisit->format('d M Y') : '—' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card h-100 border-left-warning">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Loyalty Points</div>
                    <div class="h4 mb-0 font-weight-bold">{{ number_format($loyaltyPoints) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        {{-- ── LEFT COLUMN: Client Info + Loyalty ───────────────── --}}
        <div class="col-lg-4 mb-4">

            {{-- Client Details --}}
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <strong><i class="fas fa-id-card mr-1"></i> Client Details</strong>
                    <a href="{{ route('clients.edit', $client) }}" class="btn btn-xs btn-outline-secondary">
                        <i class="fas fa-edit"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted" style="width:40%">ID</td>
                            <td><code>#{{ $client->id }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Gender</td>
                            <td>{{ $client->gender ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Date of Birth</td>
                            <td>{{ optional($client->dob)->format('d M Y') ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Mobile</td>
                            <td class="text-monospace">{{ $client->mobile }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Email</td>
                            <td class="text-monospace" style="word-break:break-all;">{{ $client->email ?? '—' }}</td>
                        </tr>
                        @if($client->address || $client->city)
                        <tr>
                            <td class="text-muted">Address</td>
                            <td>{{ implode(', ', array_filter([$client->address, $client->city])) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted">Registered</td>
                            <td>{{ optional($client->registration_date)->format('d M Y') ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- Loyalty Card --}}
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-star mr-1 text-warning"></i> Loyalty Status</strong>
                </div>
                <div class="card-body">
                    @if($loyaltyPoints > 0 || $currentTier)
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-weight-bold" style="font-size:1.5rem;">
                                {{ number_format($loyaltyPoints) }} pts
                            </span>
                            @if($currentTier)
                                <span class="badge badge-warning px-3 py-2" style="font-size:.85rem;">
                                    {{ $currentTier->name }}
                                </span>
                            @else
                                <span class="badge badge-secondary px-3 py-2">No Tier</span>
                            @endif
                        </div>

                        @if($nextTier && $pointsToNext !== null)
                            <div class="mb-1 small text-muted">
                                {{ number_format($pointsToNext) }} pts to reach <strong>{{ $nextTier->name }}</strong>
                            </div>
                            @php
                                $tierStart = $currentTier?->points_min ?? 0;
                                $tierEnd   = $nextTier->points_min;
                                $progress  = $tierEnd > $tierStart
                                    ? round(($loyaltyPoints - $tierStart) / ($tierEnd - $tierStart) * 100)
                                    : 100;
                            @endphp
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-warning" style="width:{{ $progress }}%"></div>
                            </div>
                            <div class="small text-muted mt-1">{{ $progress }}% to {{ $nextTier->name }}</div>
                        @else
                            <div class="small text-success mt-1">
                                <i class="fas fa-trophy mr-1"></i> Top tier reached!
                            </div>
                        @endif

                        @if($allTiers->isNotEmpty())
                            <hr class="my-2">
                            <div class="small text-muted mb-1">All Tiers</div>
                            @foreach($allTiers as $tier)
                                <div class="d-flex justify-content-between small {{ $currentTier?->id === $tier->id ? 'font-weight-bold text-warning' : 'text-muted' }}">
                                    <span>{{ $tier->name }}</span>
                                    <span>{{ number_format($tier->points_min) }} pts</span>
                                </div>
                            @endforeach
                        @endif
                    @else
                        <p class="text-muted mb-0 small">No loyalty points yet.</p>
                    @endif
                </div>
            </div>

            {{-- Notes & Comments --}}
            @if($client->notes || $client->comments)
            <div class="card mb-4">
                <div class="card-header"><strong><i class="fas fa-sticky-note mr-1"></i> Notes</strong></div>
                <div class="card-body">
                    @if($client->notes)
                        <div class="mb-2">
                            <div class="small text-muted font-weight-bold mb-1">Notes</div>
                            <p class="mb-0 small" style="white-space:pre-wrap;">{{ $client->notes }}</p>
                        </div>
                    @endif
                    @if($client->comments)
                        @if($client->notes)<hr class="my-2">@endif
                        <div>
                            <div class="small text-muted font-weight-bold mb-1">Comments</div>
                            <p class="mb-0 small" style="white-space:pre-wrap;">{{ $client->comments }}</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif

        </div>{{-- /col-lg-4 --}}

        {{-- ── RIGHT COLUMN: History tabs ──────────────────────── --}}
        <div class="col-lg-8">

            {{-- Tabs --}}
            <ul class="nav nav-tabs mb-0" id="profileTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="appt-tab" data-toggle="tab" href="#appt" role="tab">
                        <i class="fas fa-calendar-alt mr-1"></i> Appointments
                        <span class="badge badge-secondary ml-1">{{ $client->appointments->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="sales-tab" data-toggle="tab" href="#sales" role="tab">
                        <i class="fas fa-receipt mr-1"></i> Sales
                        <span class="badge badge-secondary ml-1">{{ $client->sales->count() }}</span>
                    </a>
                </li>
                @if($client->loyaltyTransactions->isNotEmpty())
                <li class="nav-item">
                    <a class="nav-link" id="loyalty-tab" data-toggle="tab" href="#loyalty-history" role="tab">
                        <i class="fas fa-star mr-1"></i> Points History
                    </a>
                </li>
                @endif
            </ul>

            <div class="tab-content border border-top-0 rounded-bottom">

                {{-- APPOINTMENTS TAB --}}
                <div class="tab-pane fade show active p-0" id="appt" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-dark">
                            <tr>
                                <th style="width:140px;">Date & Time</th>
                                <th>Service</th>
                                <th>Staff</th>
                                <th style="width:110px;">Status</th>
                                <th style="width:80px;" class="text-center">Sale</th>
                                <th style="width:60px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($client->appointments as $appt)
                            <tr>
                                <td class="text-nowrap small">
                                    {{ optional($appt->start_at)->format('d M Y') }}<br>
                                    <span class="text-muted">{{ optional($appt->start_at)->format('H:i') }}</span>
                                </td>
                                <td>{{ $appt->service?->name ?? '—' }}</td>
                                <td>{{ $appt->staff?->user?->name ?? '—' }}</td>
                                <td>
                                    @php
                                        $statusColor = match($appt->status) {
                                            'completed'  => 'success',
                                            'confirmed'  => 'primary',
                                            'scheduled'  => 'info',
                                            'cancelled'  => 'danger',
                                            'no_show'    => 'warning',
                                            default      => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $statusColor }}">{{ ucfirst($appt->status) }}</span>
                                </td>
                                <td class="text-center">
                                    @if($appt->sale)
                                        <a href="{{ route('pos.receipt', $appt->sale->id) }}"
                                           class="text-success" title="View Receipt">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if(Auth::user() && (Auth::user()->role === 'admin' || Auth::user()->hasPermission('appointment.manage')))
                                        <a href="{{ route('appointments.edit', $appt) }}"
                                           class="btn btn-xs btn-outline-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted p-4">No appointments yet.</td>
                            </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- SALES TAB --}}
                <div class="tab-pane fade p-0" id="sales" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-dark">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="width:130px;">Date</th>
                                <th>Services / Products</th>
                                <th style="width:90px;" class="text-right">Total</th>
                                <th style="width:90px;" class="text-center">Status</th>
                                <th style="width:60px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($client->sales as $sale)
                            <tr class="{{ $sale->is_voided ? 'table-danger' : '' }}">
                                <td><code>{{ $sale->id }}</code></td>
                                <td class="small text-nowrap">{{ $sale->created_at->format('d M Y H:i') }}</td>
                                <td class="small">
                                    @foreach($sale->saleServices as $ss)
                                        <span class="badge badge-light border mr-1">{{ $ss->service?->name ?? '?' }}</span>
                                    @endforeach
                                    @foreach($sale->saleProducts as $sp)
                                        <span class="badge badge-light border mr-1">{{ $sp->product?->name ?? '?' }}</span>
                                    @endforeach
                                    @if($sale->saleServices->isEmpty() && $sale->saleProducts->isEmpty())
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right font-weight-bold">€{{ number_format($sale->grand_total, 2) }}</td>
                                <td class="text-center">
                                    @if($sale->is_voided)
                                        <span class="badge badge-danger">Voided</span>
                                    @elseif($sale->balance_due > 0)
                                        <span class="badge badge-warning">Partial</span>
                                    @else
                                        <span class="badge badge-success">Paid</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('pos.receipt', $sale->id) }}"
                                       class="btn btn-xs btn-outline-secondary" title="Receipt">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                </td>
                            </tr>

                            {{-- Payment methods row --}}
                            @if($sale->salePayments->isNotEmpty())
                            <tr class="{{ $sale->is_voided ? 'table-danger' : '' }}" style="font-size:.8rem;opacity:.75;">
                                <td></td>
                                <td colspan="5" class="text-muted pb-2 pt-1">
                                    <i class="fas fa-credit-card mr-1"></i>
                                    @foreach($sale->salePayments as $payment)
                                        {{ $payment->paymentMethod?->name ?? '?' }}
                                        €{{ number_format($payment->amount, 2) }}{{ !$loop->last ? ' · ' : '' }}
                                    @endforeach
                                </td>
                            </tr>
                            @endif

                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted p-4">No sales yet.</td>
                            </tr>
                            @endforelse
                            </tbody>
                            @if($client->sales->isNotEmpty())
                            <tfoot class="thead-dark">
                            <tr>
                                <td colspan="3" class="text-right font-weight-bold">Total (excl. voided)</td>
                                <td class="text-right font-weight-bold text-success">€{{ number_format($totalSpent, 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- LOYALTY HISTORY TAB --}}
                @if($client->loyaltyTransactions->isNotEmpty())
                <div class="tab-pane fade p-0" id="loyalty-history" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="thead-dark">
                            <tr>
                                <th style="width:140px;">Date</th>
                                <th>Reason</th>
                                <th style="width:100px;" class="text-right">Points</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($client->loyaltyTransactions as $tx)
                            <tr>
                                <td class="small text-nowrap">{{ $tx->created_at->format('d M Y H:i') }}</td>
                                <td class="small">{{ $tx->reason }}</td>
                                <td class="text-right font-weight-bold {{ $tx->change >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $tx->change >= 0 ? '+' : '' }}{{ $tx->change }}
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

            </div>{{-- /tab-content --}}
        </div>{{-- /col-lg-8 --}}

    </div>{{-- /row --}}
</div>
@endsection
