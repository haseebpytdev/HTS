@extends('layouts.admin')

@section('title', 'Landing Pages')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Landing Pages</h1>
        <a href="{{ route('admin.landing-pages.create') }}" class="btn btn-sm btn-primary">New Landing Page</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Published</th><th></th></tr></thead>
                <tbody>
                @forelse($pages as $page)
                    <tr>
                        <td>{{ $page->title }}</td>
                        <td><code>{{ $page->slug }}</code></td>
                        <td>{{ ucfirst($page->status) }}</td>
                        <td>{{ optional($page->published_at)->format('d M Y H:i') ?? '-' }}</td>
                        <td><a href="{{ route('admin.landing-pages.edit', $page) }}" class="btn btn-sm btn-outline-secondary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No landing pages yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $pages->links() }}</div>
    </div>
@endsection
