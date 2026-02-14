@extends('layouts.app')

@section('title', 'SMS Logs')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">SMS Logs</h1>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th class="text-center" colspan="2">SMS Information</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Sent</td><td>{{ $sentCount }}</td></tr>
                    <tr><td>Delivered</td><td>{{ $deliveredCount }}</td></tr>
                    <tr><td>Failed</td><td>{{ $failedCount }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Successful SMS</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="successTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mobile</th>
                            <th>Provider</th>
                            <th>Code</th>
                            <th>Sent At</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($successLogs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td>{{ $log->mobile }}</td>
                            <td>{{ $log->provider }}</td>
                            <td>{{ $log->success_code }}</td>
                            <td>{{ optional($log->sent_at)->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-muted small mt-2">Showing last 1000 successful messages.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Failed SMS</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="failureTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mobile</th>
                            <th>Provider</th>
                            <th>Error Message</th>
                            <th>Failed At</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($failureLogs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td>{{ $log->mobile }}</td>
                            <td>{{ $log->provider }}</td>
                            <td>{{ $log->error_message }}</td>
                            <td>{{ optional($log->failed_at)->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-muted small mt-2">Showing last 1000 failed messages.</div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function(){
  $('#successTable').DataTable({ pageLength: 25, order: [[4,'desc']] });
  $('#failureTable').DataTable({ pageLength: 25, order: [[4,'desc']] });
});
</script>
@endpush
