@props(['package'])

@php
    $cover = $package->images->firstWhere('is_cover', true) ?? $package->images->first();
    $image = $cover?->image_path ? \App\Support\Media::url($cover->image_path) : null;
@endphp

<article class="card h-100 border-0 shadow-sm destination-surface-card">
    @if($image)
        <img src="{{ $image }}" class="card-img-top" alt="{{ $cover?->alt_text ?: $package->title }}" style="height: 230px; object-fit: cover;">
    @else
        <div class="card-img-top destination-fallback-visual" style="height: 230px;"></div>
    @endif
    <div class="card-body d-flex flex-column">
        <div class="small text-muted mb-2">
            {{ $package->destination?->name ?? 'Destination TBA' }}
            @if($package->duration_days)
                • {{ $package->duration_days }} days
            @endif
        </div>
        <h5 class="card-title">{{ $package->title }}</h5>
        <p class="card-text text-muted">{{ $package->excerpt ?: 'Explore this package with custom options and flexible departures.' }}</p>
        <div class="mt-auto d-flex justify-content-between align-items-center">
            <strong class="text-brand-navy">{{ number_format((float) $package->base_price, 0) }} {{ $package->currency }}</strong>
            <a href="{{ route('frontend.packages.show', $package->slug) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill px-3">View</a>
        </div>
    </div>
</article>
