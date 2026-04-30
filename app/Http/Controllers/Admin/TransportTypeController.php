<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterTransportTypeRequest;
use App\Http\Requests\Admin\StoreTransportTypeRequest;
use App\Http\Requests\Admin\UpdateTransportTypeRequest;
use App\Models\TransportType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransportTypeController extends Controller
{
    public function index(FilterTransportTypeRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $transportTypes = TransportType::query()
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where('name', 'like', '%'.$q.'%'))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.transport-types.index', [
            'transportTypes' => $transportTypes,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.transport-types.create');
    }

    public function store(StoreTransportTypeRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $transportType = new TransportType();
        $this->persistTransportType($transportType, $request->validated());

        return redirect()->route('admin.transport-types.edit', $transportType)
            ->with('success', 'Transport type created successfully.');
    }

    public function edit(TransportType $transportType): View
    {
        $this->authorize('access-admin-area');

        return view('admin.transport-types.edit', compact('transportType'));
    }

    public function update(UpdateTransportTypeRequest $request, TransportType $transportType): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->persistTransportType($transportType, $request->validated());

        return redirect()->route('admin.transport-types.edit', $transportType)
            ->with('success', 'Transport type updated successfully.');
    }

    public function destroy(TransportType $transportType): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $transportType->delete();

        return redirect()->route('admin.transport-types.index')
            ->with('success', 'Transport type deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistTransportType(TransportType $transportType, array $data): void
    {
        $transportType->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if (blank($transportType->slug) || $transportType->isDirty('name')) {
            $transportType->slug = $this->uniqueSlug((string) $data['name'], $transportType->id);
        }

        $transportType->save();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'transport-type';
        $candidate = $slug;
        $counter = 2;

        while (TransportType::query()->where('slug', $candidate)->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
