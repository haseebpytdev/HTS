@extends('layouts.admin')

@section('title', 'Groups — CMS')

@section('admin-content')
    <h1 class="h4 mb-3">Groups</h1>
    <p class="text-muted small mb-3">Manage group image galleries for the public detail page.</p>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Package</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($groups as $group)
                <tr>
                    <td>{{ $group->name }}</td>
                    <td>{{ $group->package->title ?? '—' }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.groups.gallery.index', $group) }}" class="btn btn-sm btn-outline-primary">Gallery</a>
                        <a href="{{ route('frontend.groups.show', $group->slug) }}" class="btn btn-sm btn-link" target="_blank" rel="noopener">View</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $groups->links() }}</div>
@endsection
