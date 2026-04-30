@php
    $t = $traveler;
@endphp
<div>
    <x-input-label for="first_name" value="First name" />
    <x-text-input id="first_name" class="block mt-1 w-full" type="text" name="first_name" :value="old('first_name', $t->first_name ?? '')" required />
    <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
</div>
<div>
    <x-input-label for="last_name" value="Last name" />
    <x-text-input id="last_name" class="block mt-1 w-full" type="text" name="last_name" :value="old('last_name', $t->last_name ?? '')" required />
    <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
</div>
<div>
    <x-input-label for="date_of_birth" value="Date of birth (optional)" />
    <x-text-input id="date_of_birth" class="block mt-1 w-full" type="date" name="date_of_birth" :value="old('date_of_birth', $t->date_of_birth?->format('Y-m-d') ?? '')" />
    <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
</div>
<div>
    <x-input-label for="passport_no" value="Passport number (optional)" />
    <x-text-input id="passport_no" class="block mt-1 w-full" type="text" name="passport_no" :value="old('passport_no', $t->passport_no ?? '')" />
    <x-input-error :messages="$errors->get('passport_no')" class="mt-2" />
</div>
<div>
    <x-input-label for="nationality" value="Nationality (optional)" />
    <x-text-input id="nationality" class="block mt-1 w-full" type="text" name="nationality" :value="old('nationality', $t->nationality ?? '')" />
    <x-input-error :messages="$errors->get('nationality')" class="mt-2" />
</div>
<div>
    <x-input-label for="traveler_type" value="Type" />
    <select id="traveler_type" name="traveler_type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
        @foreach(['adult', 'child', 'infant'] as $type)
            <option value="{{ $type }}" @selected(old('traveler_type', $t->traveler_type ?? 'adult') === $type)>{{ ucfirst($type) }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('traveler_type')" class="mt-2" />
</div>
