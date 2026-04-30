@props([
    'label' => null,
    'helper' => null,
    'id' => null,
])

<x-ui.input
    :label="$label"
    :helper="$helper"
    :id="$id"
    type="date"
    {{ $attributes }}
/>
