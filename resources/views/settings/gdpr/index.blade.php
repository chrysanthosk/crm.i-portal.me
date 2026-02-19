@extends('layouts.app')

@section('title', 'GDPR Data Purge')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">GDPR Data Purge</h1>
    </div>

    <div class="alert alert-warning">
        <div class="font-weight-bold mb-1">
            Warning: This action anonymizes personal data (PII) for a client.
        </div>
        <div class="small">
            It keeps the client record (and links from appointments/sales) but replaces PII fields with placeholders.
            Use only when you have a valid GDPR request.
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Search</strong></div>
        <div class="card-body">
            <form method="GET" action="{{ route('settings.gdpr.index') }}" class="form-inline" style="gap:10px;">
                <input type="text"
                       name="q"
                       value="{{ $q }}"
                       class="form-control"
                       style="min-width:320px;"
                       placeholder="Search by name, email, mobile">

                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-search mr-1"></i> Search
                </button>

                <a class="btn btn-outline-secondary" href="{{ route('settings.gdpr.index') }}">
                    Clear
                </a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Clients</strong></div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th style="width:70px;">#</th>
                        <th>First</th>
                        <th>Last</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th style="width:120px;">Gender</th>
                        <th style="width:170px;">Registered</th>
                        <th style="width:210px;" class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($clients as $c)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td>{{ $c->first_name }}</td>
                            <td>{{ $c->last_name }}</td>
                            <td class="text-monospace">{{ $c->email }}</td>
                            <td class="text-monospace">{{ $c->mobile }}</td>
                            <td><span class="badge bg-secondary">{{ $c->gender }}</span></td>
                            <td>{{ optional($c->registration_date)->format('Y-m-d H:i') }}</td>
                            <td class="text-end">

                                <form method="POST"
                                      action="{{ route('settings.gdpr.clients.purge', $c) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Anonymize (GDPR purge) client #{{ $c->id }}? This cannot be undone.');">
                                    @csrf

                                    <input type="hidden" name="confirm" value="1">

                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-user-secret mr-1"></i> Purge (Anonymize)
                                    </button>
                                </form>

                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted p-4">No clients found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($clients, 'links'))
            <div class="card-footer">{{ $clients->links() }}</div>
        @endif
    </div>

</div>
@endsection
