@props(['group'])

@php
    $cover = $group->images->firstWhere('is_cover', true) ?? $group->images->first();
    $image = $cover?->image_path ? \App\Support\Media::url($cover->image_path) : null;
@endphp

<article class="card h-100 border-0 shadow-sm destination-surface-card">
    @if($image)
        <img src="{{ $image }}" class="card-img-top" alt="{{ $cover?->alt_text ?: $group->name }}" style="height: 230px; object-fit: cover;">
    @else
        <div class="card-img-top destination-fallback-visual" style="height: 230px;"></div>
    @endif
    <div class="card-body d-flex flex-column">
        <div class="small text-muted mb-2">
            {{ $group->package?->title ?? 'No package linked' }}
        </div>
        <h5 class="card-title">{{ $group->name }}</h5>
        <p class="card-text text-muted mb-2">
            {{ ucfirst($group->status) }}
            @if($group->departure_date)
                • {{ $group->departure_date->format('d M Y') }}
            @endif
        </p>
        <div class="mt-auto d-flex justify-content-between align-items-center">
            <strong class="text-brand-navy">{{ (int) ($group->seats_left ?? 0) }} seats left</strong>
            <a href="{{ route('frontend.groups.show', $group->slug) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill px-3">View</a>
        </div>
    </div>
</article>
