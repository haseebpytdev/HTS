@php
    $editing = isset($hotel) && $hotel->exists;
    $action = $editing ? route('admin.hotels.update', $hotel) : route('admin.hotels.store');
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $action }}" class="row g-3">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="col-md-6">
                <label class="form-label">Hotel name</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $hotel->name ?? '') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $hotel->city ?? '') }}">
                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Star rating</label>
                <input type="number" min="1" max="7" name="star_rating" class="form-control @error('star_rating') is-invalid @enderror" value="{{ old('star_rating', $hotel->star_rating ?? '') }}">
                @error('star_rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-8">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control @error('address') is-invalid @enderror" rows="2">{{ old('address', $hotel->address ?? '') }}</textarea>
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Distance from Haram (km)</label>
                <input type="number" step="0.01" min="0" name="distance_from_haram_km" class="form-control @error('distance_from_haram_km') is-invalid @enderror" value="{{ old('distance_from_haram_km', $hotel->distance_from_haram_km ?? '') }}">
                @error('distance_from_haram_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <div class="form-check mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $hotel->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>

            @if($errors->any())
                <div class="col-12">
                    <div class="alert alert-danger mb-0">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create hotel' }}</button>
                <a href="{{ route('admin.hotels.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
