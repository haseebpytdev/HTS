@props([
    'title',
    'subtitle',
    'href' => null,
    'price' => null,
])

@if($href)
    <a href="{{ $href }}" class="text-decoration-none text-reset d-block h-100 deal-card-link">
@endif
<article class="deal-card h-100 shadow-sm">
    <div class="deal-card__visual"></div>
    <div class="deal-card__body">
        @if($price)
            <div class="deal-card__price">{{ $price }}</div>
        @endif
        <h3 class="deal-card__title h6 fw-bold mb-1">{{ $title }}</h3>
        <p class="deal-card__subtitle small text-secondary mb-0">{{ $subtitle }}</p>
        <span class="deal-card__cta small fw-semibold text-brand-green mt-2 d-inline-flex align-items-center gap-1">
            View <i class="bi bi-arrow-right-short fs-5"></i>
        </span>
    </div>
</article>
@if($href)
    </a>
@endif
