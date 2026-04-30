@props([
    'title' => null,
    'subtitle' => null,
])

<x-ui.flight-search-card
    :title="$title"
    :subtitle="$subtitle"
    {{ $attributes->class('as-search-form-card') }}
>
    {{ $slot }}
</x-ui.flight-search-card>
