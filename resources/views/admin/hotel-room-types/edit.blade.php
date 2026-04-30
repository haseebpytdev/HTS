@extends('layouts.admin')

@section('title', 'Edit Hotel Room Type')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Hotel Room Type</h1>
        <a href="{{ route('admin.hotel-room-types.show', $roomType) }}" class="btn btn-outline-dark">View</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @include('admin.hotel-room-types._form', ['roomType' => $roomType])
@endsection
