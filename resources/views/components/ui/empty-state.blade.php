@props([
    'title' => 'No data available',
    'message' => 'There is nothing to show right now. Try adjusting filters or adding a new record.',
    'icon' => 'bi-info-circle',
])

<div {{ $attributes->merge(['class' => 'as-empty-state text-center']) }}>
    <i class="bi {{ $icon }} d-inline-block mb-3 fs-3 text-secondary"></i>
    <div class="as-empty-state__title mb-2">{{ $title }}</div>
    <p class="as-empty-state__text mb-0">{{ $message }}</p>
    @if(trim((string) $slot) !== '')
        <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
