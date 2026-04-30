@props([
    'title',
    'subtitle' => null,
    'actions' => null,
])

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap justify-content-between align-items-start gap-3 mb-4']) }}>
    <div>
        <h1 class="as-page-title mb-2">{{ $title }}</h1>
        @if($subtitle)
            <p class="as-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if($actions)
        <div class="d-flex flex-wrap gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
