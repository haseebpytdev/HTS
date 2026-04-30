@extends('layouts.customer')

@section('title', 'Edit traveler')

@section('customer-content')
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Edit traveler</h1>
    <form method="POST" action="{{ route('customer.saved-travelers.update', $traveler) }}" class="max-w-lg bg-white p-6 rounded-lg shadow border border-gray-200 space-y-4">
        @csrf
        @method('PUT')
        @include('customer.saved-travelers._form', ['traveler' => $traveler])
        <div class="flex gap-3">
            <x-primary-button type="submit">Update</x-primary-button>
            <a href="{{ route('customer.saved-travelers.index') }}" class="text-sm text-gray-600 hover:text-gray-900 self-center">Cancel</a>
        </div>
    </form>
@endsection
