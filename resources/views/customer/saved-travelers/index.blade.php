@extends('layouts.customer')

@section('title', 'Saved travelers')

@section('customer-content')
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Saved travelers</h1>
        <a href="{{ route('customer.saved-travelers.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Add traveler</a>
    </div>
    @if(session('success'))
        <div class="mb-4 p-4 bg-green-50 text-green-800 rounded-md text-sm">{{ session('success') }}</div>
    @endif
    @if($travelers->isEmpty())
        <p class="text-gray-600">No saved travelers yet.</p>
    @else
        <div class="bg-white shadow rounded-lg border border-gray-200 divide-y divide-gray-200">
            @foreach($travelers as $t)
                <div class="p-4 flex flex-wrap justify-between gap-4 items-center">
                    <div>
                        <p class="font-medium text-gray-900">{{ $t->first_name }} {{ $t->last_name }}</p>
                        <p class="text-sm text-gray-500">{{ $t->traveler_type }} @if($t->passport_no) · Passport {{ $t->passport_no }} @endif</p>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <a href="{{ route('customer.saved-travelers.edit', $t) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                        <form method="POST" action="{{ route('customer.saved-travelers.destroy', $t) }}" onsubmit="return confirm('Remove this traveler?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
