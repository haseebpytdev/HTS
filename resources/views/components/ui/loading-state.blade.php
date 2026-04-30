@props([
    'label' => 'Loading...',
])

<div {{ $attributes->class('as-loading-state d-inline-flex align-items-center gap-2') }}>
    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
    <span class="small">{{ $label }}</span>
</div>
