@extends('layouts.app')
@section('title','Payment Methods')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Payment Methods</h1>
    <a href="{{ route('settings.payment-methods.create') }}" class="btn btn-primary">
      <i class="fas fa-plus"></i> Add
    </a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th style="width:80px;">ID</th>
            <th>Name</th>
            <th style="width:200px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($methods as $m)
            <tr>
              <td>{{ $m->id }}</td>
              <td>{{ $m->name }}</td>
              <td>
                <a class="btn btn-sm btn-info" href="{{ route('settings.payment-methods.edit', $m->id) }}">
                  Edit
                </a>
                <form method="POST" action="{{ route('settings.payment-methods.destroy', $m->id) }}" class="d-inline"
                      onsubmit="return confirm('Delete this payment method?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-danger">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="3" class="text-center text-muted p-4">No payment methods yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
