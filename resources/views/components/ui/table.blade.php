@props([
    'responsive' => true,
    'class' => '',
])

@if($responsive)
<div class="table-responsive as-table-wrap">
@endif
    <table {{ $attributes->class('table table-sm align-middle as-table '.$class) }}>
        {{ $slot }}
    </table>
@if($responsive)
</div>
@endif
