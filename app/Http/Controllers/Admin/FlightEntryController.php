<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterFlightEntryRequest;
use App\Http\Requests\Admin\StoreFlightEntryRequest;
use App\Http\Requests\Admin\UpdateFlightEntryRequest;
use App\Models\FlightEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FlightEntryController extends Controller
{
    public function index(FilterFlightEntryRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $flightEntries = FlightEntry::query()
            ->when($filters['q'] ?? null, function ($query, string $q): void {
                $query->where(function ($nested) use ($q): void {
                    $nested->where('airline', 'like', '%'.$q.'%')
                        ->orWhere('origin', 'like', '%'.$q.'%')
                        ->orWhere('destination', 'like', '%'.$q.'%')
                        ->orWhere('flight_no', 'like', '%'.$q.'%');
                });
            })
            ->when($filters['airline'] ?? null, fn ($query, string $airline) => $query->where('airline', 'like', '%'.$airline.'%'))
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($filters['depart_from'] ?? null, fn ($query, string $from) => $query->whereDate('depart_at', '>=', $from))
            ->when($filters['depart_to'] ?? null, fn ($query, string $to) => $query->whereDate('depart_at', '<=', $to))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.flights.index', [
            'flightEntries' => $flightEntries,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.flights.create');
    }

    public function store(StoreFlightEntryRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $flightEntry = new FlightEntry();
        $this->persistFlightEntry($flightEntry, $request->validated());

        return redirect()->route('admin.flights.edit', $flightEntry)
            ->with('success', 'Flight entry created successfully.');
    }

    public function show(FlightEntry $flight): View
    {
        $this->authorize('access-admin-area');

        return view('admin.flights.show', ['flightEntry' => $flight]);
    }

    public function edit(FlightEntry $flight): View
    {
        $this->authorize('access-admin-area');

        return view('admin.flights.edit', ['flightEntry' => $flight]);
    }

    public function update(UpdateFlightEntryRequest $request, FlightEntry $flight): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->persistFlightEntry($flight, $request->validated());

        return redirect()->route('admin.flights.edit', $flight)
            ->with('success', 'Flight entry updated successfully.');
    }

    public function destroy(FlightEntry $flight): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $flight->delete();

        return redirect()->route('admin.flights.index')
            ->with('success', 'Flight entry deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistFlightEntry(FlightEntry $flightEntry, array $data): void
    {
        $flightEntry->fill([
            'airline' => $data['airline'],
            'origin' => strtoupper((string) $data['origin']),
            'destination' => strtoupper((string) $data['destination']),
            'depart_at' => $data['depart_at'] ?? null,
            'arrive_at' => $data['arrive_at'] ?? null,
            'price' => $data['price'],
            'currency' => strtoupper((string) $data['currency']),
            'flight_no' => $data['flight_no'] ?? null,
            'cabin_class' => $data['cabin_class'] ?? 'economy',
            'seats_available' => $data['seats_available'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();
    }
}
