@props([
    'context' => 'nav',
])

@php
    $full = (string) config('brand.name', 'Hayat Travel Solutions');
    $words = explode(' ', $full, 2);
    $line1 = $words[0] ?? 'Hayat';
    $line2 = $words[1] ?? 'Travel Solutions';
@endphp

@php
    $wrapClass = match ($context) {
        'footer' => 'brand-wordmark brand-wordmark--footer',
        'admin' => 'brand-wordmark brand-wordmark--admin',
        default => 'brand-wordmark',
    };
@endphp

<div {{ $attributes->merge(['class' => $wrapClass]) }}>
    <span class="brand-wordmark__icon" aria-hidden="true">
        <svg class="brand-wordmark__h" viewBox="0 0 32 32" width="32" height="32" focusable="false" xmlns="http://www.w3.org/2000/svg">
            <rect x="4" y="5" width="4.5" height="22" rx="0" fill="currentColor"/>
            <rect x="23.5" y="5" width="4.5" height="22" rx="0" fill="currentColor"/>
            <rect x="4" y="14" width="24" height="4" rx="0" fill="currentColor"/>
        </svg>
    </span>
    <span class="brand-wordmark__text">
        <span class="brand-wordmark__line1">{{ $line1 }}</span>
        <span class="brand-wordmark__line2">{{ $line2 }}</span>
    </span>
</div>
