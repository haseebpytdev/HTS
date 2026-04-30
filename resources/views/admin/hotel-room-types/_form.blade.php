@php
    $editing = isset($roomType) && $roomType->exists;
    $action = $editing ? route('admin.hotel-room-types.update', $roomType) : route('admin.hotel-room-types.store');
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $action }}" class="row g-3">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="col-md-4">
                <label class="form-label">Hotel</label>
                <select name="hotel_id" class="form-select @error('hotel_id') is-invalid @enderror" required>
                    <option value="">Select hotel</option>
                    @foreach($hotels as $hotel)
                        <option value="{{ $hotel->id }}" @selected((string) old('hotel_id', $roomType->hotel_id ?? '') === (string) $hotel->id)>{{ $hotel->name }}</option>
                    @endforeach
                </select>
                @error('hotel_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Room type name</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $roomType->name ?? '') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Sharing basis</label>
                <select name="sharing_basis" class="form-select @error('sharing_basis') is-invalid @enderror" required>
                    <option value="">Select sharing basis</option>
                    @foreach($sharingOptions as $label => $capacity)
                        <option value="{{ $label }}" @selected(old('sharing_basis', array_search((int) ($roomType->base_capacity ?? 2), $sharingOptions, true) ?: 'double') === $label)>{{ ucfirst($label) }}</option>
                    @endforeach
                </select>
                @error('sharing_basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Children capacity</label>
                <input type="number" min="0" max="10" name="max_children" class="form-control @error('max_children') is-invalid @enderror" value="{{ old('max_children', $roomType->max_children ?? 0) }}">
                @error('max_children')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-9">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description', $roomType->description ?? '') }}">
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $roomType->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create room type' }}</button>
                <a href="{{ route('admin.hotel-room-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
