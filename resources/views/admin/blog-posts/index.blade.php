@extends('layouts.admin')

@section('title', 'Blog Posts')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Blog Posts</h1>
        <a href="{{ route('admin.blog-posts.create') }}" class="btn btn-sm btn-primary">New Post</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Published</th><th></th></tr></thead>
                <tbody>
                @forelse($posts as $post)
                    <tr>
                        <td>{{ $post->title }}</td>
                        <td><code>{{ $post->slug }}</code></td>
                        <td>{{ ucfirst($post->status) }}</td>
                        <td>{{ optional($post->published_at)->format('d M Y H:i') ?? '-' }}</td>
                        <td><a href="{{ route('admin.blog-posts.edit', $post) }}" class="btn btn-sm btn-outline-secondary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No blog posts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $posts->links() }}</div>
    </div>
@endsection
