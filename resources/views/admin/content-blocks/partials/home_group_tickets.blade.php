@php
    $p = $block->payload ?? [];
    $items = old('items', $p['items'] ?? []);
@endphp
<div class="mb-2">
    <label class="form-label">Section title</label>
    <input type="text" name="section_title" class="form-control" value="{{ old('section_title', $p['section_title'] ?? '') }}">
</div>
<p class="small text-muted mb-2">Cards — use link to <code>/groups</code> or a filtered URL</p>
@for($i = 0; $i < 8; $i++)
    @php $row = $items[$i] ?? []; @endphp
    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <label class="form-label small">Title</label>
            <input type="text" name="items[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $row['title'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Tag</label>
            <input type="text" name="items[{{ $i }}][tag]" class="form-control form-control-sm" value="{{ $row['tag'] ?? '' }}">
        </div>
        <div class="col-md-6">
            <label class="form-label small">Link</label>
            <input type="text" name="items[{{ $i }}][link]" class="form-control form-control-sm" value="{{ $row['link'] ?? '' }}" placeholder="{{ url('/groups') }}">
        </div>
    </div>
@endfor
