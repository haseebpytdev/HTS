@extends('layouts.admin')

@section('title', 'SEO pages')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">SEO pages</h1>
        <a href="{{ route('admin.seo-pages.create') }}" class="btn btn-primary btn-sm">Add SEO page</a>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search key or title" value="{{ $filters['q'] ?? '' }}">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
        </div>
    </form>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>Key</th>
                <th>Title</th>
                <th>Meta title</th>
                <th>Index</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($pages as $page)
                <tr>
                    <td><code>{{ $page->page_key }}</code></td>
                    <td>{{ $page->title ?? '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($page->meta_title ?? '', 48) }}</td>
                    <td>{{ $page->is_indexable ? 'Yes' : 'No' }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.seo-pages.edit', $page) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('admin.seo-pages.destroy', $page) }}" class="d-inline" onsubmit="return confirm('Delete this SEO record?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">No SEO pages. Run <code>php artisan db:seed --class=CmsSeeder</code> or create one.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $pages->links() }}</div>
@endsection
