<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerSavedTravelerRequest;
use App\Http\Requests\Customer\UpdateCustomerSavedTravelerRequest;
use App\Models\CustomerSavedTraveler;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerSavedTravelerController extends Controller
{
    public function index(): View
    {
        $travelers = auth('customer')->user()->savedTravelers()->orderBy('sort_order')->get();

        return view('customer.saved-travelers.index', compact('travelers'));
    }

    public function create(): View
    {
        return view('customer.saved-travelers.create');
    }

    public function store(StoreCustomerSavedTravelerRequest $request): RedirectResponse
    {
        $customer = auth('customer')->user();
        $max = (int) $customer->savedTravelers()->max('sort_order');

        $customer->savedTravelers()->create(array_merge($request->validated(), [
            'sort_order' => $max + 1,
        ]));

        return redirect()->route('customer.saved-travelers.index')
            ->with('success', 'Traveler saved.');
    }

    public function edit(CustomerSavedTraveler $savedTraveler): View
    {
        return view('customer.saved-travelers.edit', ['traveler' => $savedTraveler]);
    }

    public function update(UpdateCustomerSavedTravelerRequest $request, CustomerSavedTraveler $savedTraveler): RedirectResponse
    {
        $savedTraveler->update($request->validated());

        return redirect()->route('customer.saved-travelers.index')
            ->with('success', 'Traveler updated.');
    }

    public function destroy(CustomerSavedTraveler $savedTraveler): RedirectResponse
    {
        $savedTraveler->delete();

        return redirect()->route('customer.saved-travelers.index')
            ->with('success', 'Traveler removed.');
    }
}
