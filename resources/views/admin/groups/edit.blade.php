@extends('layouts.admin')

@section('title', 'Edit Group')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Group</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.groups.show', $group) }}" class="btn btn-outline-dark">View</a>
            <a href="{{ route('admin.groups.gallery.index', $group) }}" class="btn btn-outline-info">Gallery</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.groups.update', $group) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-6">
                    <label class="form-label">Group name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $group->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Package (destination linked)</label>
                    <select name="package_id" class="form-select @error('package_id') is-invalid @enderror" required>
                        <option value="">Select package</option>
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}" @selected((string) old('package_id', $group->package_id) === (string) $package->id)>
                                {{ $package->title }} ({{ $package->destination?->name ?? 'No destination' }})
                            </option>
                        @endforeach
                    </select>
                    @error('package_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Departure date</label>
                    <input type="date" name="departure_date" class="form-control @error('departure_date') is-invalid @enderror" value="{{ old('departure_date', $group->departure_date?->toDateString()) }}">
                    @error('departure_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Return date</label>
                    <input type="date" name="return_date" class="form-control @error('return_date') is-invalid @enderror" value="{{ old('return_date', $group->return_date?->toDateString()) }}">
                    @error('return_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Capacity</label>
                    <input type="number" name="capacity" min="1" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity', $group->capacity) }}" required>
                    @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Seats left</label>
                    <input type="number" name="seats_left" min="0" class="form-control @error('seats_left') is-invalid @enderror" value="{{ old('seats_left', $group->seats_left) }}" required>
                    @error('seats_left')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Pricing tiers</label>
                    <textarea name="pricing_tiers" rows="3" class="form-control @error('pricing_tiers') is-invalid @enderror">{{ old('pricing_tiers', $pricing_tiers) }}</textarea>
                    @error('pricing_tiers')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Airline info</label>
                    <textarea name="airline_info" rows="3" class="form-control @error('airline_info') is-invalid @enderror">{{ old('airline_info', $airline_info) }}</textarea>
                    @error('airline_info')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hotel info</label>
                    <textarea name="hotel_info" rows="3" class="form-control @error('hotel_info') is-invalid @enderror">{{ old('hotel_info', $hotel_info) }}</textarea>
                    @error('hotel_info')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $notes_text) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-2">
                    <div class="form-check mt-2">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1" @checked((bool) old('is_featured', $group->status === 'featured'))>
                        <label for="is_featured" class="form-check-label">Featured</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $group->status !== 'closed'))>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save Changes</button>
                    <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
@endsection
