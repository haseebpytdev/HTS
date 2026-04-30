@props([
    'title',
    'subtitle' => null,
])

<section {{ $attributes->class('content-shell p-3 p-md-4') }}>
    <h3 class="as-card-title mb-1">{{ $title }}</h3>
    @if($subtitle)
        <p class="as-helper-text mb-3">{{ $subtitle }}</p>
    @endif
    {{ $slot }}
</section>
