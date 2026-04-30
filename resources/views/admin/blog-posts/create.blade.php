@extends('layouts.admin')

@section('title', 'Create Blog Post')

@section('admin-content')
    <h1 class="h4 mb-3">Create Blog Post</h1>
    <form method="POST" action="{{ route('admin.blog-posts.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body">
            @include('admin.blog-posts.partials.form', ['post' => null])
        </div>
        <div class="card-footer"><button class="btn btn-sm btn-primary">Create</button></div>
    </form>
@endsection
