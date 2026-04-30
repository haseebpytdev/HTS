@extends('layouts.admin')

@section('title', 'Create Package')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Create Package</h1>
    </div>

    @include('admin.packages._form')
@endsection
