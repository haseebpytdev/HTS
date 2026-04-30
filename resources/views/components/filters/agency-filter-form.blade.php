@props(['filters' => []])

<form method="GET" action="{{ route('admin.agencies.index') }}" class="row g-2 align-items-end mb-3">
    <div class="col-md-5">
        <label class="form-label">Search</label>
        <input
            type="text"
            name="q"
            class="form-control"
            value="{{ $filters['q'] ?? '' }}"
            placeholder="Search by name or code"
        >
    </div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select">
            <option value="">All</option>
            <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
            <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
        </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="{{ route('admin.agencies.index') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>
