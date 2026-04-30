@extends('layouts.admin')

@section('title', 'Edit Hotel Rate')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Hotel Rate</h1>
        <a href="{{ route('admin.hotel-rates.show', $hotelRate) }}" class="btn btn-outline-dark">View</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @include('admin.hotel-rates._form', ['hotelRate' => $hotelRate])
@endsection
