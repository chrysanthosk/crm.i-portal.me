@extends('layouts.app')

@section('title', 'Operational Reports')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0">Operational Reports</h1>
                <div class="text-muted small">Select a report and filters.</div>
            </div>
        </div>
    </div>
</div>

@php
    // Friendly column labels for auto-generated tables
    $colLabels = [
        'id' => 'ID',
        'sale_id' => 'Sale #',
        'sales_id' => 'Sale #',
        'sale_date' => 'Sale Date',
        'sales_date' => 'Sale Date',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'date' => 'Date',
        'date_from' => 'From',
        'date_to' => 'To',
        'report_number' => 'Report #',
        'client_name' => 'Client',
        'client' => 'Client',
        'staff_id' => 'Staff',
        'name' => 'Name',
        'gender' => 'Gender',
        'count' => 'Count',
        'appt_count' => 'Appointments',
        'first_appt_date' => 'First Appointment',
        'total_paid' => 'Total Paid (€)',
        'total_revenue' => 'Revenue (€)',
        'revenue' => 'Revenue (€)',
        'amount' => 'Amount (€)',
        'payment_method' => 'Payment Method',
    ];

    $prettyCol = function ($key) use ($colLabels) {
        $k = (string)$key;
        if (isset($colLabels[$k])) return $colLabels[$k];

        // fallback: snake_case -> Title Case
        $k2 = str_replace('_', ' ', $k);
        return ucwords($k2);
    };
@endphp

<div class="container-fluid">

    <div class="card">
        <div class="card-body">
            <form id="reportFilterForm" method="GET" class="form-row align-items-end">

                <div class="form-group col-md-4">
                    <label for="report">Report</label>
                    <select id="report" name="report" class="form-control">
                        <option value="">-- Select a Report --</option>

                        <optgroup label="Top Lists">
                            <option value="top_clients_appts"    {{ $selectedReport==='top_clients_appts' ? 'selected' : '' }}>Top 10 Clients (Appointments)</option>
                            <option value="top_clients_payments" {{ $selectedReport==='top_clients_payments' ? 'selected' : '' }}>Top 10 Clients (Payments)</option>
                            <option value="top_staff_appts"      {{ $selectedReport==='top_staff_appts' ? 'selected' : '' }}>Top 10 Staff (Appointments)</option>
                            <option value="top_staff_payments"   {{ $selectedReport==='top_staff_payments' ? 'selected' : '' }}>Top 10 Staff (Payments)</option>
                            <option value="first_appointments"   {{ $selectedReport==='first_appointments' ? 'selected' : '' }}>First Appointments (New Clients)</option>
                            <option value="gender_distribution"  {{ $selectedReport==='gender_distribution' ? 'selected' : '' }}>Gender Distribution</option>
                        </optgroup>

                        <optgroup label="Sales">
                            <option value="sales_appointments" {{ $selectedReport==='sales_appointments' ? 'selected' : '' }}>Sales (Appointments)</option>
                            <option value="sales_products"     {{ $selectedReport==='sales_products' ? 'selected' : '' }}>Sales (Products)</option>
                        </optgroup>

                        <optgroup label="Cashier">
                            <option value="cashier_all"      {{ $selectedReport==='cashier_all' ? 'selected' : '' }}>Cashier (All)</option>
                            <option value="cashier_staff"    {{ $selectedReport==='cashier_staff' ? 'selected' : '' }}>Cashier (Staff)</option>
                            <option value="cashier_service"  {{ $selectedReport==='cashier_service' ? 'selected' : '' }}>Cashier (Service)</option>
                            <option value="cashier_products" {{ $selectedReport==='cashier_products' ? 'selected' : '' }}>Cashier (Products)</option>
                        </optgroup>

                        <optgroup label="Z Reports">
                            <option value="z_reports"          {{ $selectedReport==='z_reports' ? 'selected' : '' }}>Generate Z Report</option>
                            <option value="generated_zreports" {{ $selectedReport==='generated_zreports' ? 'selected' : '' }}>Generated Z Reports</option>
                        </optgroup>
                    </select>
                </div>

                {{-- Date filters (hide for generated_zreports, because it lists all) --}}
                @php
                    $hideDates = in_array($selectedReport, ['generated_zreports'], true);
                @endphp

                <div class="form-group col-md-3 {{ $hideDates ? 'd-none' : '' }}">
                    <label for="from_date">From</label>
                    <input id="from_date" type="date" name="from_date" class="form-control"
                           value="{{ request('from_date', now()->toDateString()) }}">
                </div>

                <div class="form-group col-md-3 {{ $hideDates ? 'd-none' : '' }}">
                    <label for="to_date">To</label>
                    <input id="to_date" type="date" name="to_date" class="form-control"
                           value="{{ request('to_date', now()->toDateString()) }}">
                </div>

                {{-- Conditional filters --}}
                @php
                    $showStaff   = $selectedReport === 'cashier_staff';
                    $showService = $selectedReport === 'cashier_service';
                    $showProduct = $selectedReport === 'cashier_products';
                @endphp

                <div class="form-group col-md-3 {{ $showStaff ? '' : 'd-none' }}">
                    <label for="staff_id">Staff</label>
                    <select id="staff_id" name="staff_id" class="form-control">
                        <option value="0">-- All --</option>
                        @foreach(($filters['staff'] ?? []) as $s)
                            <option value="{{ $s['id'] }}" {{ (int)request('staff_id',0)===(int)$s['id'] ? 'selected' : '' }}>
                                {{ $s['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-3 {{ $showService ? '' : 'd-none' }}">
                    <label for="service_id">Service</label>
                    <select id="service_id" name="service_id" class="form-control">
                        <option value="0">-- All --</option>
                        @foreach(($filters['services'] ?? []) as $s)
                            <option value="{{ $s['id'] }}" {{ (int)request('service_id',0)===(int)$s['id'] ? 'selected' : '' }}>
                                {{ $s['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-3 {{ $showProduct ? '' : 'd-none' }}">
                    <label for="product_id">Product</label>
                    <select id="product_id" name="product_id" class="form-control">
                        <option value="0">-- All --</option>
                        @foreach(($filters['products'] ?? []) as $p)
                            <option value="{{ $p['id'] }}" {{ (int)request('product_id',0)===(int)$p['id'] ? 'selected' : '' }}>
                                {{ $p['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-2">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sync-alt mr-1"></i> Load
                    </button>
                </div>

            </form>
        </div>
    </div>

    {{-- Z Report generator UI --}}
    @if($selectedReport === 'z_reports')
        <div class="card">
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    Select a date range and click <b>Generate</b>. A new Z report will be created and you can print it.
                </div>

                <button id="btnGenerateZ" class="btn btn-primary">
                    <i class="fas fa-file-invoice mr-1"></i> Generate Z Report
                </button>

                <div id="zMsg" class="mt-3 text-muted small"></div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            @if(($data['type'] ?? '') === 'table')
                <div class="p-3">
                    @if(isset($data['from']) && isset($data['to']))
                        <div class="text-muted small">
                            From: {{ $data['from'] }} — To: {{ $data['to'] }}
                        </div>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr>
                            @php
                                $first = $data['rows'][0] ?? null;
                                $cols = $first ? array_keys((array)$first) : [];
                            @endphp

                            @foreach($cols as $c)
                                <th>{{ $prettyCol($c) }}</th>
                            @endforeach

                            @if(($data['actions'] ?? false) === true)
                                <th style="width:120px">Action</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach(($data['rows'] ?? []) as $row)
                            <tr>
                                @foreach((array)$row as $v)
                                    <td>{!! is_string($v) ? e($v) : (is_numeric($v) ? $v : e((string)$v)) !!}</td>
                                @endforeach

                                @if(($data['actions'] ?? false) === true)
                                    <td>
                                        @if(isset($row->id))
                                            <a class="btn btn-sm btn-outline-secondary"
                                               target="_blank"
                                               href="{{ route('reports.zreport.print', ['id' => $row->id]) }}">
                                                <i class="fas fa-print"></i>
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('reports.zreport.delete', ['id' => $row->id]) }}"
                                                  style="display:inline-block"
                                                  onsubmit="return confirm('Delete this Z Report?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- PDF only when meaningful --}}
                @if(!in_array($selectedReport, ['z_reports'], true) && $selectedReport)
                    <div class="p-3">
                        <a class="btn btn-sm btn-outline-primary"
                           href="{{ route('reports.pdf', ['report' => $selectedReport]) }}?{{ http_build_query(request()->query()) }}">
                            <i class="fas fa-file-pdf mr-1"></i> Download PDF
                        </a>
                    </div>
                @endif

            @elseif(($data['type'] ?? '') === 'empty')
                <div class="p-3 text-muted">{{ $data['message'] ?? 'Select a report.' }}</div>
            @else
                <div class="p-3 text-muted">Select a report from the dropdown.</div>
            @endif
        </div>
    </div>

</div>
@endsection

@push('scripts')
{{-- Z Report generator JS --}}
@if($selectedReport === 'z_reports')
<script>
(function () {
    const btn = document.getElementById('btnGenerateZ');
    const msg = document.getElementById('zMsg');
    const fromEl = document.getElementById('from_date');
    const toEl = document.getElementById('to_date');

    if (!btn) return;

    btn.addEventListener('click', async () => {
        const from = fromEl?.value;
        const to = toEl?.value;

        if (!from || !to) {
            alert('Please select From/To dates.');
            return;
        }

        btn.disabled = true;
        msg.textContent = 'Generating...';

        try {
            const r = await fetch("{{ route('reports.zreport.generate') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ from_date: from, to_date: to })
            });

            const j = await r.json();
            if (!r.ok || !j.success) {
                throw new Error(j.error || 'Failed');
            }

            msg.innerHTML = 'Created Z Report #' + j.report_number +
                ' — <a target="_blank" href="' + j.print_url + '">Print</a>';

            window.open(j.print_url, '_blank');
        } catch (e) {
            msg.textContent = 'Error: ' + e.message;
            alert('Error generating Z report: ' + e.message);
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
@endif
@endpush
