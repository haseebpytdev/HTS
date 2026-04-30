<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterTransportRateRequest;
use App\Http\Requests\Admin\StoreTransportRateRequest;
use App\Http\Requests\Admin\UpdateTransportRateRequest;
use App\Models\TransportRate;
use App\Models\TransportType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransportRateController extends Controller
{
    public function index(FilterTransportRateRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $transportRates = TransportRate::query()
            ->with('transportType:id,name')
            ->when($filters['transport_type_id'] ?? null, fn ($query, int $typeId) => $query->where('transport_type_id', $typeId))
            ->when($filters['label'] ?? null, function ($query, string $label): void {
                $query->where(function ($nested) use ($label): void {
                    $nested->where('vehicle_name', 'like', '%'.$label.'%')
                        ->orWhere('route_from', 'like', '%'.$label.'%')
                        ->orWhere('route_to', 'like', '%'.$label.'%');
                });
            })
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($filters['valid_from'] ?? null, fn ($query, string $from) => $query->whereDate('valid_from', '>=', $from))
            ->when($filters['valid_to'] ?? null, fn ($query, string $to) => $query->whereDate('valid_to', '<=', $to))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.transport-rates.index', [
            'transportRates' => $transportRates,
            'transportTypes' => TransportType::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.transport-rates.create', [
            'transportTypes' => TransportType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreTransportRateRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $transportRate = new TransportRate();
        $this->persistTransportRate($transportRate, $request->validated());

        return redirect()->route('admin.transport-rates.edit', $transportRate)
            ->with('success', 'Transport rate created successfully.');
    }

    public function edit(TransportRate $transportRate): View
    {
        $this->authorize('access-admin-area');

        return view('admin.transport-rates.edit', [
            'transportRate' => $transportRate,
            'transportTypes' => TransportType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateTransportRateRequest $request, TransportRate $transportRate): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->persistTransportRate($transportRate, $request->validated());

        return redirect()->route('admin.transport-rates.edit', $transportRate)
            ->with('success', 'Transport rate updated successfully.');
    }

    public function destroy(TransportRate $transportRate): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $transportRate->delete();

        return redirect()->route('admin.transport-rates.index')
            ->with('success', 'Transport rate deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistTransportRate(TransportRate $transportRate, array $data): void
    {
        $transportRate->fill([
            'transport_type_id' => $data['transport_type_id'],
            'vehicle_name' => $data['label'],
            'route_from' => $data['route_from'] ?? null,
            'route_to' => $data['route_to'] ?? null,
            'trip_type' => $data['trip_type'] ?? null,
            'currency' => strtoupper((string) $data['currency']),
            'amount' => $data['amount'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();
    }
}
