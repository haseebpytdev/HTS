@extends('layouts.admin')

@section('title', 'Edit Integration Connection')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Edit Integration Connection</h1>
        <a href="{{ route('admin.integrations.show', $connection) }}" class="btn btn-outline-dark">View</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    <p class="text-muted small mb-3">For Sabre, keep credentials aligned to Developer Hub User ID and Password, and keep Pricing/Booking disabled until search-only validation is complete.</p>

    @include('admin.integrations._form', ['isCreate' => false])
@endsection
