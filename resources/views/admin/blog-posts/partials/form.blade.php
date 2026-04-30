<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control form-control-sm" value="{{ old('title', $post?->title) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control form-control-sm" value="{{ old('slug', $post?->slug) }}" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select form-select-sm">
            @php($status = old('status', $post?->status ?? 'draft'))
            <option value="draft" @selected($status==='draft')>Draft</option>
            <option value="published" @selected($status==='published')>Published</option>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Excerpt</label>
        <textarea name="excerpt" rows="2" class="form-control form-control-sm">{{ old('excerpt', $post?->excerpt) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Body</label>
        <textarea name="body" rows="10" class="form-control form-control-sm">{{ old('body', $post?->body) }}</textarea>
    </div>
</div>
