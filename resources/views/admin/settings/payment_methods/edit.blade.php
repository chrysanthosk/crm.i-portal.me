@extends('layouts.app')
@section('title','Edit Payment Method')

@section('content')
<div class="container-fluid">
  <div class="mb-3">
    <h1 class="h3">Edit Payment Method</h1>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="POST" action="{{ route('settings.payment-methods.update', $method->id) }}">
        @csrf @method('PUT')

        <div class="form-group">
          <label>Name</label>
          <input name="name" class="form-control" value="{{ old('name', $method->name) }}" required maxlength="100">
        </div>

        <button class="btn btn-success"><i class="fas fa-save"></i> Save</button>
        <a href="{{ route('settings.payment-methods.index') }}" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  </div>
</div>
@endsection
