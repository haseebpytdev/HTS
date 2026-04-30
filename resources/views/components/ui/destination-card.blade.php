@props([
    'title',
    'subtitle' => null,
    'image' => null,
    'href' => '#',
    'badge' => null,
])

<a href="{{ $href }}" {{ $attributes->class('destination-card-link text-decoration-none') }}>
    <article class="destination-card h-100">
        @if($image)
            <img src="{{ $image }}" alt="{{ $title }}" class="destination-card__image">
        @else
            <div class="destination-card__image destination-card__image--placeholder"></div>
        @endif
        <div class="destination-card__overlay"></div>
        <div class="destination-card__content">
            @if($badge)
                <span class="destination-card__badge">{{ $badge }}</span>
            @endif
            <h3 class="destination-card__title mb-1">{{ $title }}</h3>
            @if($subtitle)
                <p class="destination-card__subtitle mb-0">{{ $subtitle }}</p>
            @endif
        </div>
    </article>
</a>
