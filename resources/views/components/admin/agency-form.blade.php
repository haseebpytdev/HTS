@props([
    'agency' => null,
    'action',
    'method' => 'POST',
    'submitLabel' => 'Save',
])

<form method="POST" action="{{ $action }}" class="card card-body shadow-sm">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Agency Name</label>
            <input
                type="text"
                name="name"
                class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $agency?->name) }}"
                required
            >
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">Agency Code</label>
            <input
                type="text"
                name="code"
                class="form-control @error('code') is-invalid @enderror"
                value="{{ old('code', $agency?->code) }}"
                required
            >
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <div class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="is_active"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', $agency?->is_active ?? true))
                >
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-success">{{ $submitLabel }}</button>
        <a href="{{ route('admin.agencies.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
