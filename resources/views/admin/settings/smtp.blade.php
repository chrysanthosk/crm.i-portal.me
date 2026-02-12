@extends('layouts.app')

@section('title', 'SMTP Settings')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">SMTP</h1>
    </div>

    @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
    <div class="alert alert-danger">Please fix the errors below.</div>
    @endif

    <div class="card">
        <div class="card-header"><strong>SMTP Settings</strong></div>

        <div class="card-body">

            {{-- UPDATE SETTINGS --}}
            <form method="POST" action="{{ route('settings.smtp.update') }}">
                @csrf
                @method('PUT')

                <div class="form-check mb-3">
                    <input type="hidden" name="enabled" value="0">
                    <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                           @checked(old('enabled', (int)($smtp?->enabled ?? 0)) === 1)>
                    <label class="form-check-label" for="enabled">Enable SMTP</label>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Host</label>
                        <input class="form-control" name="host" value="{{ old('host', $smtp->host ?? '') }}">
                        @error('host') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Port</label>
                        <input class="form-control" name="port" value="{{ old('port', $smtp->port ?? '') }}">
                        @error('port') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Encryption</label>
                        <select class="form-control" name="encryption">
                            @php $enc = old('encryption', $smtp->encryption ?? 'tls'); @endphp
                            <option value="">None</option>
                            <option value="tls" @selected($enc === 'tls')>TLS</option>
                            <option value="ssl" @selected($enc === 'ssl')>SSL</option>
                        </select>
                        @error('encryption') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" value="{{ old('username', $smtp->username ?? '') }}">
                        @error('username') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input class="form-control" name="password" type="password" value="{{ old('password', $smtp->password ?? '') }}">
                        @error('password') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">From Email</label>
                        <input class="form-control" name="from_email" value="{{ old('from_email', $smtp->from_email ?? '') }}">
                        @error('from_email') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">From Name</label>
                        <input class="form-control" name="from_name" value="{{ old('from_name', $smtp->from_name ?? '') }}">
                        @error('from_name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>

                <button class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Save SMTP Settings
                </button>

            </form>

            <hr>

            {{-- TEST EMAIL --}}
            <form method="POST" action="{{ route('settings.smtp.test') }}" class="mt-3">
                @csrf
                <div class="row">
                    <div class="col-md-8 mb-2">
                        <label class="form-label">Send test email to</label>
                        <input class="form-control" name="to" value="{{ old('to', auth()->user()->email) }}" required>
                        @error('to') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end mb-2">
                        <button class="btn btn-outline-secondary w-100">
                            <i class="fas fa-paper-plane me-2"></i> Send Test Email
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
