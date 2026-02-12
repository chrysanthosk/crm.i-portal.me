@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Audit Log</h1>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Filters</strong></div>
        <div class="card-body">
            <form method="GET" action="{{ route('settings.audit.index') }}">
                <div class="row">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="">All</option>
                            @foreach(($categories ?? []) as $c)
                            <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Action</label>
                        <input name="action" class="form-control" value="{{ request('action') }}" placeholder="e.g. user.create">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Actor User ID</label>
                        <input name="user_id" class="form-control" value="{{ request('user_id') }}" placeholder="e.g. 1">
                    </div>

                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Entries</strong></div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th style="width:80px;">#</th>
                        <th style="width:180px;">Time</th>
                        <th style="width:160px;">Category</th>
                        <th style="width:220px;">Action</th>
                        <th>Message</th>
                        <th style="width:160px;">Actor</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td class="text-monospace">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->category }}</td>
                        <td class="text-monospace">{{ $log->action }}</td>
                        <td>{{ $log->message ?? '' }}</td>
                        <td>
                            @if($log->user)
                            {{ $log->user->name }}
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted p-4">No audit logs found.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($logs, 'links'))
        <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    </div>

</div>
@endsection
