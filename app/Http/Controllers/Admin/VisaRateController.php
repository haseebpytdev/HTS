<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterVisaRateRequest;
use App\Http\Requests\Admin\StoreVisaRateRequest;
use App\Http\Requests\Admin\UpdateVisaRateRequest;
use App\Models\VisaRate;
use App\Models\VisaType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VisaRateController extends Controller
{
    public function index(FilterVisaRateRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $visaRates = VisaRate::query()
            ->with('visaType:id,name')
            ->when($filters['visa_type_id'] ?? null, fn ($query, int $visaTypeId) => $query->where('visa_type_id', $visaTypeId))
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($filters['valid_from'] ?? null, fn ($query, string $from) => $query->whereDate('valid_from', '>=', $from))
            ->when($filters['valid_to'] ?? null, fn ($query, string $to) => $query->whereDate('valid_to', '<=', $to))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.visa-rates.index', [
            'visaRates' => $visaRates,
            'visaTypes' => VisaType::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.visa-rates.create', [
            'visaTypes' => VisaType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreVisaRateRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $visaRate = new VisaRate();
        $this->persistVisaRate($visaRate, $request->validated());

        return redirect()->route('admin.visa-rates.edit', $visaRate)
            ->with('success', 'Visa rate created successfully.');
    }

    public function edit(VisaRate $visaRate): View
    {
        $this->authorize('access-admin-area');

        return view('admin.visa-rates.edit', [
            'visaRate' => $visaRate,
            'visaTypes' => VisaType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateVisaRateRequest $request, VisaRate $visaRate): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->persistVisaRate($visaRate, $request->validated());

        return redirect()->route('admin.visa-rates.edit', $visaRate)
            ->with('success', 'Visa rate updated successfully.');
    }

    public function destroy(VisaRate $visaRate): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $visaRate->delete();

        return redirect()->route('admin.visa-rates.index')
            ->with('success', 'Visa rate deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistVisaRate(VisaRate $visaRate, array $data): void
    {
        $visaRate->fill([
            'visa_type_id' => $data['visa_type_id'],
            'currency' => strtoupper((string) $data['currency']),
            'amount' => $data['amount'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();
    }
}
