@extends('layouts.admin')

@section('title', 'Edit Package')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Package</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.packages.show', $package) }}" class="btn btn-outline-dark">View</a>
            <a href="{{ route('admin.packages.gallery.index', $package) }}" class="btn btn-outline-info">Gallery</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @include('admin.packages._form', ['package' => $package])
@endsection
