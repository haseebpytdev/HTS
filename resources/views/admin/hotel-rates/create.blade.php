@extends('layouts.admin')

@section('title', 'Create Hotel Rate')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Create Hotel Rate</h1>
    </div>

    @include('admin.hotel-rates._form')
@endsection
