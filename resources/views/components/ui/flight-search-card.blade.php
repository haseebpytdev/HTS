@props([
    'title' => 'Search live flights',
    'subtitle' => 'Supplier-backed search with real-time fare revalidation.',
])

<section {{ $attributes->class('as-flight-search-card') }}>
    <header class="mb-3">
        <h2 class="as-card-title mb-1">{{ $title }}</h2>
        <p class="as-helper-text mb-0">{{ $subtitle }}</p>
    </header>
    {{ $slot }}
</section>
