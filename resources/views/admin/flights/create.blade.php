@extends('layouts.admin')

@section('title', 'Create Flight Entry')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Create Flight Entry</h1>
    </div>

    @include('admin.flights._form')
@endsection
