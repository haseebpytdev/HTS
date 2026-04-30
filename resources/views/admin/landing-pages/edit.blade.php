@extends('layouts.admin')

@section('title', 'Edit Landing Page')

@section('admin-content')
    <h1 class="h4 mb-3">Edit Landing Page</h1>
    <form method="POST" action="{{ route('admin.landing-pages.update', $page) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('admin.landing-pages.partials.form', ['page' => $page])
        </div>
        <div class="card-footer"><button class="btn btn-sm btn-primary">Save</button></div>
    </form>
@endsection
