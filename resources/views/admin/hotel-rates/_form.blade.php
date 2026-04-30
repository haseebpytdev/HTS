@php
    $hotelRate = $hotelRate ?? null;
    $editing = $hotelRate?->exists ?? false;
    $action = $editing ? route('admin.hotel-rates.update', $hotelRate) : route('admin.hotel-rates.store');
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $action }}" class="row g-3">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="col-md-6">
                <label class="form-label">Hotel</label>
                <select id="hotel_select" class="form-select">
                    <option value="">Select hotel</option>
                    @foreach($hotels as $hotel)
                        <option value="{{ $hotel->id }}" @selected((string) old('hotel_id', $hotelRate?->roomType?->hotel_id ?? '') === (string) $hotel->id)>{{ $hotel->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Room type</label>
                <select name="hotel_room_type_id" id="room_type_select" class="form-select @error('hotel_room_type_id') is-invalid @enderror" required>
                    <option value="">Select room type</option>
                    @foreach($roomTypes as $roomType)
                        <option data-hotel-id="{{ $roomType->hotel_id }}" value="{{ $roomType->id }}" @selected((string) old('hotel_room_type_id', $hotelRate->hotel_room_type_id ?? '') === (string) $roomType->id)>{{ $roomType->name }} ({{ $roomType->hotel?->name ?? 'Hotel' }})</option>
                    @endforeach
                </select>
                @error('hotel_room_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Season name</label>
                <input type="text" name="season_name" class="form-control @error('season_name') is-invalid @enderror" value="{{ old('season_name', $hotelRate?->season_name ?? '') }}" required>
                @error('season_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label class="form-label">Currency</label>
                <input type="text" name="currency" maxlength="3" class="form-control @error('currency') is-invalid @enderror" value="{{ old('currency', $hotelRate->currency ?? 'PKR') }}" required>
                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Rate per night</label>
                <input type="number" step="0.01" min="0" name="rate_per_night" class="form-control @error('rate_per_night') is-invalid @enderror" value="{{ old('rate_per_night', $hotelRate->rate_per_night ?? '') }}" required>
                @error('rate_per_night')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <div class="form-check mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $hotelRate->is_active ?? true))>
                    <label for="is_active" class="form-check-label">Active</label>
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label">Valid from</label>
                <input type="date" name="valid_from" class="form-control @error('valid_from') is-invalid @enderror" value="{{ old('valid_from', $hotelRate?->valid_from?->toDateString() ?? '') }}" required>
                @error('valid_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Valid to</label>
                <input type="date" name="valid_to" class="form-control @error('valid_to') is-invalid @enderror" value="{{ old('valid_to', $hotelRate?->valid_to?->toDateString() ?? '') }}">
                @error('valid_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create rate' }}</button>
                <a href="{{ route('admin.hotel-rates.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const hotelSelect = document.getElementById('hotel_select');
        const roomTypeSelect = document.getElementById('room_type_select');
        if (!hotelSelect || !roomTypeSelect) return;

        const filterRoomTypes = function () {
            const hotelId = hotelSelect.value;
            Array.from(roomTypeSelect.options).forEach(function (option) {
                const optionHotelId = option.getAttribute('data-hotel-id');
                if (!optionHotelId || !hotelId) {
                    option.hidden = false;
                    return;
                }
                option.hidden = optionHotelId !== hotelId;
            });
        };

        hotelSelect.addEventListener('change', filterRoomTypes);
        filterRoomTypes();
    })();
</script>
