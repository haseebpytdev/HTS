@props([
    'tone' => 'muted',
    'pill' => true,
])

@php
    $toneClass = match ($tone) {
        'success' => 'as-chip as-chip--success',
        'danger' => 'as-chip as-chip--danger',
        'warning' => 'as-chip as-chip--warning',
        'info' => 'as-chip as-chip--info',
        'primary' => 'as-chip as-chip--primary',
        default => 'as-chip as-chip--muted',
    };
@endphp

<span {{ $attributes->merge(['class' => trim(($pill ? '' : 'rounded-3 ').$toneClass)]) }}>
    {{ $slot }}
</span>
