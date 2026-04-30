@props([
    'label' => null,
    'helper' => null,
    'id' => null,
    'type' => 'text',
])

@php
    $inputId = $id ?: 'input-'.md5((string) $attributes->get('name').'-'.(string) microtime(true));
@endphp

<div class="as-field">
    @if($label)
        <label for="{{ $inputId }}" class="form-label as-label mb-1">{{ $label }}</label>
    @endif
    <input id="{{ $inputId }}" type="{{ $type }}" {{ $attributes->class('form-control as-input') }}>
    @if($helper)
        <p class="as-helper-text mt-1 mb-0">{{ $helper }}</p>
    @endif
</div>
