@props([
    'tone' => 'info',
    'title' => null,
])

@php
    $classes = match ($tone) {
        'success' => 'alert as-alert as-alert--success',
        'warning' => 'alert as-alert as-alert--warning',
        'danger' => 'alert as-alert as-alert--danger',
        default => 'alert as-alert as-alert--info',
    };
@endphp

<div {{ $attributes->class($classes) }}>
    @if($title)
        <div class="fw-semibold mb-1">{{ $title }}</div>
    @endif
    {{ $slot }}
</div>
