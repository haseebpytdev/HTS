@props([
    'label' => null,
    'helper' => null,
])

<fieldset {{ $attributes->class('as-choice-group') }}>
    @if($label)
        <legend class="as-label mb-2">{{ $label }}</legend>
    @endif
    <div class="d-flex flex-wrap gap-3">
        {{ $slot }}
    </div>
    @if($helper)
        <p class="as-helper-text mt-1 mb-0">{{ $helper }}</p>
    @endif
</fieldset>
