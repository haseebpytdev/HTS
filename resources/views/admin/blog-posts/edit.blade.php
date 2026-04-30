@extends('layouts.admin')

@section('title', 'Edit Blog Post')

@section('admin-content')
    <h1 class="h4 mb-3">Edit Blog Post</h1>
    <form method="POST" action="{{ route('admin.blog-posts.update', $post) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('admin.blog-posts.partials.form', ['post' => $post])
        </div>
        <div class="card-footer"><button class="btn btn-sm btn-primary">Save</button></div>
    </form>
@endsection
