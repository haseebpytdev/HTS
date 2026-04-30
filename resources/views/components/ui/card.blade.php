@props([
    'title' => null,
    'subtitle' => null,
    'tone' => 'default',
])

@php
    $toneClass = match ($tone) {
        'soft' => 'as-card as-card--soft',
        'dark' => 'as-card as-card--dark text-white',
        default => 'as-card',
    };
@endphp

<section {{ $attributes->merge(['class' => $toneClass]) }}>
    <div class="card-body">
        @if($title)
            <h2 class="as-section-title mb-1">{{ $title }}</h2>
        @endif
        @if($subtitle)
            <p class="as-helper-text mb-3">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
