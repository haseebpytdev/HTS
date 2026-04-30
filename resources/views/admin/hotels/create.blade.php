@extends('layouts.admin')

@section('title', 'Create Hotel')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Create Hotel</h1>
        <small class="text-muted">Add a new hotel record.</small>
    </div>

    @include('admin.hotels._form')
@endsection
