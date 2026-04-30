@props([
    'title',
    'price',
    'currency' => 'PKR',
    'subtitle' => null,
    'ctaLabel' => 'View details',
    'href' => '#',
])

<article {{ $attributes->class('content-shell p-3 p-md-4 h-100 pricing-package-card') }}>
    <h3 class="as-card-title mb-2">{{ $title }}</h3>
    @if($subtitle)
        <p class="as-helper-text mb-3">{{ $subtitle }}</p>
    @endif
    <p class="fw-bold fs-4 text-brand-navy mb-3">{{ number_format((float) $price, 0) }} <span class="small fw-semibold">{{ strtoupper($currency) }}</span></p>
    <a href="{{ $href }}" class="btn btn-outline-brand-navy rounded-pill px-3">{{ $ctaLabel }}</a>
</article>
