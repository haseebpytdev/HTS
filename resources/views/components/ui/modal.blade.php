@props([
    'id',
    'title' => '',
    'size' => null,
])

@php
    $dialogClass = match ($size) {
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        default => '',
    };
@endphp

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog {{ $dialogClass }} modal-dialog-centered">
        <div class="modal-content border-0 shadow as-modal-shell">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-1">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
