@extends('layouts.app')

@section('title', 'Staff Performance')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css"/>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Staff Performance</h3>
                </div>

                <div class="card-body">

                    <form method="GET" action="{{ route('reports.staff_performance') }}" class="mb-3">
                        <div class="form-row">

                            <div class="form-group col-md-3">
                                <label>From</label>
                                <input type="date" name="from_date" value="{{ request('from_date', $from ?? '') }}" class="form-control">
                            </div>

                            <div class="form-group col-md-3">
                                <label>To</label>
                                <input type="date" name="to_date" value="{{ request('to_date', $to ?? '') }}" class="form-control">
                            </div>

                            <div class="form-group col-md-3">
                                <label>Date basis</label>
                                <select name="date_basis" class="form-control">
                                    <option value="sale" {{ request('date_basis','sale') === 'sale' ? 'selected' : '' }}>Sale date (sales.created_at)</option>
                                    <option value="appointment" {{ request('date_basis','sale') === 'appointment' ? 'selected' : '' }}>Appointment date (sales.appointment_id → appointments.start_at)</option>
                                </select>
                                <small class="text-muted">Use “Appointment date” only if your sales are linked to appointments.</small>
                            </div>

                            <div class="form-group col-md-3 d-flex align-items-end">
                                <button class="btn btn-primary btn-block" type="submit">
                                    <i class="fas fa-filter mr-1"></i> Apply
                                </button>
                            </div>

                        </div>
                    </form>

                    <div class="table-responsive">
                        <table id="staffPerformanceTable" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>Staff</th>
                                <th class="text-right"># Appointments</th>
                                <th class="text-right">Service Revenue (€)</th>
                                <th class="text-right">Product Revenue (€)</th>
                                <th class="text-right">Total Revenue (€)</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach(($rows ?? []) as $r)
                                @php
                                    // staffDisplaySql returns "name" field in most cases
                                    $name = trim((string)($r->name ?? ''));
                                    if ($name === '') $name = 'Staff #' . (int)($r->staff_id ?? 0);

                                    $appt = (int)($r->appointments_count ?? 0);
                                    $srv  = (float)($r->service_revenue ?? 0);
                                    $prd  = (float)($r->product_revenue ?? 0);
                                    $tot  = (float)($r->total_revenue ?? ($srv + $prd));
                                @endphp

                                <tr>
                                    <td>{{ $name }}</td>
                                    <td class="text-right">{{ $appt }}</td>
                                    <td class="text-right">{{ number_format($srv, 2) }}</td>
                                    <td class="text-right">{{ number_format($prd, 2) }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format($tot, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(function () {
            $('#staffPerformanceTable').DataTable({
                pageLength: 10,
                order: [[4, 'desc']]
            });
        });
    </script>
@endpush
