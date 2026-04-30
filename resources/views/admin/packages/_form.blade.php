@php
    $editing = isset($package) && $package->exists;
    $action = $editing ? route('admin.packages.update', $package) : route('admin.packages.store');
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $action }}" class="row g-3">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="col-md-6">
                <label class="form-label as-label">Title</label>
                <input type="text" name="title" class="form-control as-input @error('title') is-invalid @enderror" value="{{ old('title', $package->title ?? '') }}" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label as-label">Slug</label>
                <input type="text" name="slug" class="form-control as-input @error('slug') is-invalid @enderror" value="{{ old('slug', $package->slug ?? '') }}" placeholder="auto-generated if blank">
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label as-label">Destination</label>
                <select name="destination_id" class="form-select as-select @error('destination_id') is-invalid @enderror">
                    <option value="">Select destination</option>
                    @foreach($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected((string) old('destination_id', $package->destination_id ?? '') === (string) $destination->id)>{{ $destination->name }}</option>
                    @endforeach
                </select>
                @error('destination_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label as-label">Category</label>
                <select name="category_id" class="form-select as-select @error('category_id') is-invalid @enderror">
                    <option value="">Select category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $package->category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label as-label">Duration (days)</label>
                <input type="number" min="1" max="365" name="duration_days" class="form-control as-input @error('duration_days') is-invalid @enderror" value="{{ old('duration_days', $package->duration_days ?? '') }}">
                @error('duration_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label as-label">Currency</label>
                <input type="text" maxlength="3" name="currency" class="form-control as-input @error('currency') is-invalid @enderror" value="{{ old('currency', $package->currency ?? 'PKR') }}" required>
                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label as-label">Base price</label>
                <input type="number" step="0.01" min="0" name="base_price" class="form-control as-input @error('base_price') is-invalid @enderror" value="{{ old('base_price', $package->base_price ?? '') }}" required>
                @error('base_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-12">
                <label class="form-label as-label">Overview</label>
                <textarea name="overview" rows="3" class="form-control as-input @error('overview') is-invalid @enderror">{{ old('overview', $overview ?? $package->excerpt ?? '') }}</textarea>
                @error('overview')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-12">
                <label class="form-label as-label">Itinerary</label>
                <textarea name="itinerary" rows="4" class="form-control as-input @error('itinerary') is-invalid @enderror">{{ old('itinerary', $itinerary ?? '') }}</textarea>
                @error('itinerary')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label as-label">Inclusions</label>
                <textarea name="inclusions" rows="4" class="form-control as-input @error('inclusions') is-invalid @enderror">{{ old('inclusions', $inclusions ?? '') }}</textarea>
                @error('inclusions')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label as-label">Exclusions</label>
                <textarea name="exclusions" rows="4" class="form-control as-input @error('exclusions') is-invalid @enderror">{{ old('exclusions', $exclusions ?? '') }}</textarea>
                @error('exclusions')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <div class="form-check mt-2">
                    <input type="hidden" name="is_featured" value="0">
                    <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1" @checked((bool) old('is_featured', $package->is_featured ?? false))>
                    <label for="is_featured" class="form-check-label">Featured</label>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-check mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $package->is_active ?? true))>
                    <label for="is_active" class="form-check-label">Active</label>
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">{{ $editing ? 'Save Changes' : 'Create Package' }}</button>
                <a href="{{ route('admin.packages.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
