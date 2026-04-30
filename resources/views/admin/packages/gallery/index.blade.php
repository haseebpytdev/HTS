@extends('layouts.admin')

@section('title', 'Package gallery')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Gallery — {{ $package->title }}</h1>
            <small class="text-muted">{{ $package->slug }}</small>
        </div>
        <a href="{{ route('admin.cms.packages.index') }}" class="btn btn-sm btn-outline-secondary">All packages</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Upload image</h2>
            <form method="POST" action="{{ route('admin.packages.gallery.store', $package) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">File</label>
                    <input type="file" name="image" class="form-control form-control-sm" required accept="image/*">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Alt text</label>
                    <input type="text" name="alt_text" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm" min="0">
                </div>
                <div class="col-md-2 form-check mt-4">
                    <input type="checkbox" name="is_cover" value="1" class="form-check-input" id="cov">
                    <label class="form-check-label" for="cov">Cover</label>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary btn-sm w-100">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        @forelse($package->images as $img)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <img src="{{ \App\Support\Media::url($img->image_path) }}" class="card-img-top" alt="{{ $img->alt_text }}" style="max-height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.packages.gallery.images.update', [$package, $img]) }}" class="small">
                            @csrf
                            @method('PATCH')
                            <div class="mb-1">
                                <label class="form-label mb-0">Alt</label>
                                <input type="text" name="alt_text" class="form-control form-control-sm" value="{{ $img->alt_text }}">
                            </div>
                            <div class="mb-1">
                                <label class="form-label mb-0">Sort</label>
                                <input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $img->sort_order }}">
                            </div>
                            <div class="form-check mb-2">
                                <input type="hidden" name="is_cover" value="0">
                                <input type="checkbox" name="is_cover" value="1" class="form-check-input" id="c{{ $img->id }}" @checked($img->is_cover)>
                                <label class="form-check-label" for="c{{ $img->id }}">Cover</label>
                            </div>
                            <button class="btn btn-sm btn-primary">Save</button>
                        </form>
                        <form method="POST" action="{{ route('admin.packages.gallery.images.destroy', [$package, $img]) }}" class="mt-2" onsubmit="return confirm('Delete this image?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-muted">No images yet.</div>
        @endforelse
    </div>
@endsection
