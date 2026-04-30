@props([
    'label' => null,
    'helper' => null,
    'id' => null,
    'rows' => 4,
])

@php
    $textareaId = $id ?: 'textarea-'.md5((string) $attributes->get('name').'-'.(string) microtime(true));
@endphp

<div class="as-field">
    @if($label)
        <label for="{{ $textareaId }}" class="form-label as-label mb-1">{{ $label }}</label>
    @endif
    <textarea id="{{ $textareaId }}" rows="{{ $rows }}" {{ $attributes->class('form-control as-input') }}>{{ $slot }}</textarea>
    @if($helper)
        <p class="as-helper-text mt-1 mb-0">{{ $helper }}</p>
    @endif
</div>
