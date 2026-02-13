@extends('layouts.app')

@section('title', 'Edit Product')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Edit Product</h1>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('products._form', [
                'mode' => 'edit',
                'product' => $product,
                'categories' => $categories,
                'vatTypes' => $vatTypes,
            ])
        </div>
    </div>
</div>
@endsection
