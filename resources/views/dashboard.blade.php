@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  <div class="card">
    <div class="card-header"><strong>Dashboard</strong></div>
    <div class="card-body">
      <p class="mb-0">Welcome, {{ auth()->user()->name }}.</p>
    </div>
  </div>
@endsection
