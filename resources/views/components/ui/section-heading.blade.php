@props([
    'kicker' => null,
    'title',
    'subtitle' => null,
    'align' => 'left',
])

@php
    $alignClass = $align === 'center' ? 'text-center mx-auto' : '';
@endphp

<header {{ $attributes->class('as-section-heading '.$alignClass) }}>
    @if($kicker)
        <p class="as-section-kicker mb-2">{{ $kicker }}</p>
    @endif
    <h2 class="as-section-title mb-2">{{ $title }}</h2>
    @if($subtitle)
        <p class="section-subtitle mb-0 {{ $align === 'center' ? 'mx-auto' : '' }}">{{ $subtitle }}</p>
    @endif
</header>
