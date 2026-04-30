@php
    $p = $block->payload ?? [];
@endphp
<div class="mb-2">
    <label class="form-label">Headline (optional)</label>
    <input type="text" name="headline" class="form-control" value="{{ old('headline', $p['headline'] ?? '') }}">
</div>
<div class="mb-2">
    <label class="form-label">Subheadline (optional)</label>
    <input type="text" name="subheadline" class="form-control" value="{{ old('subheadline', $p['subheadline'] ?? '') }}">
</div>
<div class="mb-2">
    <label class="form-label">Background image path</label>
    <input type="text" name="background_image" class="form-control" value="{{ old('background_image', $p['background_image'] ?? '') }}" placeholder="storage path after upload, e.g. cms/home/2026/04/...">
    <small class="text-muted">Upload below to replace; uses public disk (<code>/storage/...</code>).</small>
</div>
<div class="mb-2">
    <label class="form-label">Upload new background</label>
    <input type="file" name="background_upload" class="form-control" accept="image/*">
</div>
