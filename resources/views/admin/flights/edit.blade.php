@extends('layouts.admin')

@section('title', 'Edit Flight Entry')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Flight Entry</h1>
        <a href="{{ route('admin.flights.show', $flightEntry) }}" class="btn btn-outline-dark">View</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @include('admin.flights._form', ['flightEntry' => $flightEntry])
@endsection
