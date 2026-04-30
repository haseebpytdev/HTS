@props([
    'label' => null,
    'helper' => null,
    'id' => null,
])

@php
    $fileId = $id ?: 'file-'.md5((string) $attributes->get('name').'-'.(string) microtime(true));
@endphp

<div class="as-field">
    @if($label)
        <label for="{{ $fileId }}" class="form-label as-label mb-1">{{ $label }}</label>
    @endif
    <input id="{{ $fileId }}" type="file" {{ $attributes->class('form-control as-input') }}>
    @if($helper)
        <p class="as-helper-text mt-1 mb-0">{{ $helper }}</p>
    @endif
</div>
