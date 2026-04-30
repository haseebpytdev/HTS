@props([
    'package' => null,
    'group' => null,
])

@if (session('status'))
    <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
@endif

<input type="hidden" name="source" value="frontend">
@if($package)
    <input type="hidden" name="package_id" value="{{ $package->id }}">
@endif
@if($group)
    <input type="hidden" name="group_id" value="{{ $group->id }}">
@endif

<x-ui.validation-errors />

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.input label="Name *" name="name" value="{{ old('name') }}" required />
    </div>
    <div class="col-md-6">
        <x-ui.input label="Phone" name="phone" value="{{ old('phone') }}" />
    </div>
    <div class="col-md-6">
        <x-ui.input label="Email" type="email" name="email" value="{{ old('email') }}" />
    </div>
    <div class="col-md-6">
        <x-ui.date-input label="Travel Date" name="travel_date" value="{{ old('travel_date') }}" />
    </div>
    <div class="col-md-4">
        <x-ui.input label="Adults *" type="number" min="1" name="adults" value="{{ old('adults', 2) }}" required />
    </div>
    <div class="col-md-4">
        <x-ui.input label="Children" type="number" min="0" name="children" value="{{ old('children', 0) }}" />
    </div>
    <div class="col-md-4">
        <x-ui.input label="Budget (PKR)" type="number" min="0" step="0.01" name="budget" value="{{ old('budget') }}" />
    </div>
    <div class="col-12">
        <x-ui.textarea label="Message" name="message" rows="3">{{ old('message') }}</x-ui.textarea>
    </div>
</div>
