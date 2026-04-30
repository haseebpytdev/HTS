@extends('layouts.admin')

@section('title', 'Edit Hotel')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Edit Hotel</h1>
            <small class="text-muted">Update hotel details.</small>
        </div>
        <a href="{{ route('admin.hotels.show', $hotel) }}" class="btn btn-outline-dark">View</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @include('admin.hotels._form', ['hotel' => $hotel])
@endsection
