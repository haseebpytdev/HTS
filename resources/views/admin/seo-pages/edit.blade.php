@extends('layouts.admin')

@section('title', 'Edit SEO')

@section('admin-content')
    <h1 class="h4 mb-3">Edit SEO — {{ $seoPage->page_key }}</h1>

    <form method="POST" action="{{ route('admin.seo-pages.update', $seoPage) }}" class="bg-white p-3 rounded shadow-sm">
        @csrf
        @method('PUT')
        <div class="mb-2">
            <label class="form-label">Page key *</label>
            <input type="text" name="page_key" class="form-control" value="{{ old('page_key', $seoPage->page_key) }}" required pattern="[a-z0-9_]+">
        </div>
        <div class="mb-2">
            <label class="form-label">Internal title</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $seoPage->title) }}">
        </div>
        <div class="mb-2">
            <label class="form-label">Meta title</label>
            <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $seoPage->meta_title) }}">
        </div>
        <div class="mb-2">
            <label class="form-label">Meta description</label>
            <textarea name="meta_description" rows="3" class="form-control">{{ old('meta_description', $seoPage->meta_description) }}</textarea>
        </div>
        <div class="mb-2">
            <label class="form-label">Meta keywords</label>
            <input type="text" name="meta_keywords" class="form-control" value="{{ old('meta_keywords', $seoPage->meta_keywords) }}">
        </div>
        <div class="mb-2">
            <label class="form-label">OG image</label>
            <input type="text" name="og_image" class="form-control" value="{{ old('og_image', $seoPage->og_image) }}" placeholder="/storage/... or https://...">
        </div>
        <div class="mb-2">
            <label class="form-label">Canonical URL</label>
            <input type="text" name="canonical_url" class="form-control" value="{{ old('canonical_url', $seoPage->canonical_url) }}">
        </div>
        <div class="mb-2">
            <label class="form-label">Schema (JSON-LD)</label>
            <textarea name="schema_markup_json" rows="6" class="form-control font-monospace small">{{ old('schema_markup_json', $seoPage->schema_markup ? json_encode($seoPage->schema_markup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="is_indexable" value="0">
            <input class="form-check-input" type="checkbox" name="is_indexable" value="1" id="ix" @checked(old('is_indexable', $seoPage->is_indexable))>
            <label class="form-check-label" for="ix">Allow index (search engines)</label>
        </div>
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.seo-pages.index') }}" class="btn btn-link">Back</a>
    </form>
@endsection
