@extends('layouts.app')

@section('title', 'Create Appointment')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Create Appointment</h1>
        <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('appointments._form', [
            'mode' => 'create',
            'appointment' => new \App\Models\Appointment(),
            ])
        </div>
    </div>

</div>
@endsection
