@props([
    'filters' => [],
    'destinations' => collect(),
    'categories' => collect(),
    'action' => '#',
])

<form method="GET" action="{{ $action }}" class="row g-2 align-items-end js-ajax-filter-form" data-results="#package-list" data-pagination="#package-pagination">
    <div class="col-md-3">
        <label class="form-label mb-1">Search</label>
        <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Title or keyword">
    </div>
    <div class="col-md-2">
        <label class="form-label mb-1">Destination</label>
        <select name="destination" class="form-select">
            <option value="">All</option>
            @foreach($destinations as $destination)
                <option value="{{ $destination->slug }}" @selected(($filters['destination'] ?? '') === $destination->slug)>
                    {{ $destination->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label mb-1">Category</label>
        <select name="category" class="form-select">
            <option value="">All</option>
            @foreach($categories as $category)
                <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label mb-1">Min Price</label>
        <input type="number" min="0" name="price_min" class="form-control" value="{{ $filters['price_min'] ?? '' }}" placeholder="0">
    </div>
    <div class="col-md-2">
        <label class="form-label mb-1">Max Price</label>
        <input type="number" min="0" name="price_max" class="form-control" value="{{ $filters['price_max'] ?? '' }}" placeholder="0">
    </div>
    <div class="col-md-1">
        <label class="form-label mb-1">Sort</label>
        <select name="sort" class="form-select">
            <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>New</option>
            <option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Low</option>
            <option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>High</option>
        </select>
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4">Apply filters</button>
        <a href="{{ $action }}" class="btn btn-outline-brand-navy rounded-pill px-3">Reset</a>
    </div>
</form>
