@props([
    'label' => null,
    'helper' => null,
    'id' => null,
])

@php
    $selectId = $id ?: 'select-'.md5((string) $attributes->get('name').'-'.(string) microtime(true));
@endphp

<div class="as-field">
    @if($label)
        <label for="{{ $selectId }}" class="form-label as-label mb-1">{{ $label }}</label>
    @endif
    <select id="{{ $selectId }}" {{ $attributes->class('form-select as-select') }}>
        {{ $slot }}
    </select>
    @if($helper)
        <p class="as-helper-text mt-1 mb-0">{{ $helper }}</p>
    @endif
</div>
