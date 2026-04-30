@props([
    'title' => 'Need a custom quote?',
    'subtitle' => 'Share your route, dates, and traveler count to get curated options.',
])

<aside {{ $attributes->class('content-shell p-3 p-md-4 quote-request-card') }}>
    <h3 class="as-card-title mb-1">{{ $title }}</h3>
    <p class="as-helper-text mb-3">{{ $subtitle }}</p>
    {{ $slot }}
</aside>
