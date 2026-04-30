@props([
    'variant' => 'primary',
    'size' => 'md',
    'pill' => false,
    'full' => false,
])

@php
    $variantClasses = match ($variant) {
        'primary' => 'btn as-btn as-btn--primary',
        'secondary' => 'btn as-btn as-btn--secondary',
        'ghost' => 'btn as-btn as-btn--ghost',
        'danger' => 'btn as-btn as-btn--danger',
        default => 'btn as-btn as-btn--primary',
    };

    $sizeClasses = match ($size) {
        'sm' => 'as-btn--sm',
        'lg' => 'as-btn--lg',
        default => 'as-btn--md',
    };
@endphp

<button {{ $attributes->class(trim($variantClasses.' '.$sizeClasses.' '.($pill ? 'rounded-pill' : 'rounded-3').' '.($full ? 'w-100' : ''))) }}>
    {{ $slot }}
</button>
