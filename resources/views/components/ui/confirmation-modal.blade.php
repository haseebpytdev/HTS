@props([
    'id',
    'title' => 'Confirm action',
    'message' => 'Are you sure you want to continue?',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
])

<x-ui.modal :id="$id" :title="$title">
    <p class="mb-3">{{ $message }}</p>
    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-brand-navy rounded-pill px-3" data-bs-dismiss="modal">{{ $cancelLabel }}</button>
        @if(trim((string) $slot) !== '')
            {{ $slot }}
        @else
            <button type="button" class="btn btn-brand-green text-white rounded-pill px-3" data-bs-dismiss="modal">{{ $confirmLabel }}</button>
        @endif
    </div>
</x-ui.modal>
