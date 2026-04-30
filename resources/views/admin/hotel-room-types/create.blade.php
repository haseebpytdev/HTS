@extends('layouts.admin')

@section('title', 'Create Hotel Room Type')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Create Hotel Room Type</h1>
    </div>

    @include('admin.hotel-room-types._form')
@endsection
