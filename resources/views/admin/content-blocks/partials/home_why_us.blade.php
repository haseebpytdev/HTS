@php
    $p = $block->payload ?? [];
    $items = old('items', $p['items'] ?? []);
@endphp
<div class="mb-2">
    <label class="form-label">Section title</label>
    <input type="text" name="section_title" class="form-control" value="{{ old('section_title', $p['section_title'] ?? '') }}">
</div>
<div class="mb-3">
    <label class="form-label">Section subtitle</label>
    <input type="text" name="section_subtitle" class="form-control" value="{{ old('section_subtitle', $p['section_subtitle'] ?? '') }}">
</div>
<p class="small text-muted mb-2">Cards (leave title empty to skip row)</p>
@for($i = 0; $i < 8; $i++)
    @php
        $row = $items[$i] ?? [];
    @endphp
    <div class="row g-2 mb-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Title {{ $i + 1 }}</label>
            <input type="text" name="items[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $row['title'] ?? '' }}">
        </div>
        <div class="col-md-8">
            <label class="form-label small">Description</label>
            <input type="text" name="items[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $row['description'] ?? '' }}">
        </div>
    </div>
@endfor
