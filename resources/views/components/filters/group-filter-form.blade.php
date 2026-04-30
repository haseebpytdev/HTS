@props([
    'filters' => [],
    'action' => '#',
])

<form method="GET" action="{{ $action }}" class="row g-2 align-items-end js-ajax-filter-form" data-results="#group-list" data-pagination="#group-pagination">
    <div class="col-md-3">
        <label class="form-label mb-1">Search</label>
        <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Group or package">
    </div>
    <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select name="status" class="form-select">
            <option value="">All</option>
            @foreach(['open', 'closed', 'cancelled'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label mb-1">Departure From</label>
        <input type="date" name="departure_from" class="form-control" value="{{ $filters['departure_from'] ?? '' }}">
    </div>
    <div class="col-md-2">
        <label class="form-label mb-1">Departure To</label>
        <input type="date" name="departure_to" class="form-control" value="{{ $filters['departure_to'] ?? '' }}">
    </div>
    <div class="col-md-2">
        <label class="form-label mb-1">Sort</label>
        <select name="sort" class="form-select">
            <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Newest</option>
            <option value="earliest_departure" @selected(($filters['sort'] ?? '') === 'earliest_departure')>Earliest</option>
            <option value="seats_left" @selected(($filters['sort'] ?? '') === 'seats_left')>Seats Left</option>
        </select>
    </div>
    <div class="col-md-1">
        <label class="form-label mb-1">Rows</label>
        <select name="per_page" class="form-select">
            @foreach([12, 24, 36] as $rowCount)
                <option value="{{ $rowCount }}" @selected((int) ($filters['per_page'] ?? 12) === $rowCount)>{{ $rowCount }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4">Apply filters</button>
        <a href="{{ $action }}" class="btn btn-outline-brand-navy rounded-pill px-3">Reset</a>
    </div>
</form>
