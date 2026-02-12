@extends('layouts.app')

@section('title', 'Edit Service')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Edit Service</h1>
        <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('services._form', [
            'mode' => 'edit',
            'service' => $service,
            'categories' => $categories,
            'vatTypes' => $vatTypes,
            ])
        </div>
    </div>
</div>
@endsection
