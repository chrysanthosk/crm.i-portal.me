@extends('layouts.app')

@section('title', 'Create Client')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Create Client</h1>
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">Please fix the errors below.</div>
    @endif

    <div class="card">
        <div class="card-header"><strong>Client Details</strong></div>

        <div class="card-body">
            <form method="POST" action="{{ route('clients.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Registration Date (optional)</label>
                        <input type="datetime-local" name="registration_date" class="form-control" value="{{ old('registration_date') }}">
                        @error('registration_date') <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="text-muted small mt-1">Leave empty to use current time.</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                        @error('first_name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required>
                        @error('last_name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-control" value="{{ old('dob') }}" required>
                        @error('dob') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-control" required>
                            @foreach(['Male','Female','Other'] as $g)
                            <option value="{{ $g }}" @selected(old('gender') === $g)>{{ $g }}</option>
                            @endforeach
                        </select>
                        @error('gender') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}" required>
                        @error('mobile') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                        @error('address') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="{{ old('city') }}">
                        @error('city') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="4">{{ old('notes') }}</textarea>
                        @error('notes') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Comments</label>
                        <textarea name="comments" class="form-control" rows="4">{{ old('comments') }}</textarea>
                        @error('comments') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i> Create Client
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
