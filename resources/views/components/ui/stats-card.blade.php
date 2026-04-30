@props([
    'label',
    'value',
    'icon' => 'bi-stars',
])

<article {{ $attributes->class('stats-card h-100') }}>
    <div class="stats-card__icon">
        <i class="bi {{ $icon }}"></i>
    </div>
    <p class="stats-card__value mb-1">{{ $value }}</p>
    <p class="stats-card__label mb-0">{{ $label }}</p>
</article>
