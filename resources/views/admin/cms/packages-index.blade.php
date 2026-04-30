@extends('layouts.admin')

@section('title', 'Packages — CMS')

@section('admin-content')
    <h1 class="h4 mb-3">Packages</h1>
    <p class="text-muted small mb-3">Open the image gallery for each package. Cover image is used on listing cards.</p>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Title</th>
                <th>Slug</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($packages as $package)
                <tr>
                    <td>{{ $package->title }}</td>
                    <td><code>{{ $package->slug }}</code></td>
                    <td class="text-end">
                        <a href="{{ route('admin.packages.gallery.index', $package) }}" class="btn btn-sm btn-outline-primary">Gallery</a>
                        <a href="{{ route('frontend.packages.show', $package->slug) }}" class="btn btn-sm btn-link" target="_blank" rel="noopener">View</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $packages->links() }}</div>
@endsection
