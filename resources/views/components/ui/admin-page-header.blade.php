@props([
    'title',
    'subtitle' => null,
])

<x-ui.section-header :title="$title" :subtitle="$subtitle" {{ $attributes }}>
    <x-slot:actions>
        {{ $actions ?? '' }}
    </x-slot:actions>
</x-ui.section-header>
