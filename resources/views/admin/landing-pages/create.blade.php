@extends('layouts.admin')

@section('title', 'Create Landing Page')

@section('admin-content')
    <h1 class="h4 mb-3">Create Landing Page</h1>
    <form method="POST" action="{{ route('admin.landing-pages.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body">
            @include('admin.landing-pages.partials.form', ['page' => null])
        </div>
        <div class="card-footer"><button class="btn btn-sm btn-primary">Create</button></div>
    </form>
@endsection
