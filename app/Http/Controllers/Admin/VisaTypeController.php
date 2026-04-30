<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterVisaTypeRequest;
use App\Http\Requests\Admin\StoreVisaTypeRequest;
use App\Http\Requests\Admin\UpdateVisaTypeRequest;
use App\Models\VisaType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VisaTypeController extends Controller
{
    public function index(FilterVisaTypeRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $visaTypes = VisaType::query()
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where('name', 'like', '%'.$q.'%'))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.visa-types.index', [
            'visaTypes' => $visaTypes,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.visa-types.create');
    }

    public function store(StoreVisaTypeRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $visaType = new VisaType();
        $this->persistVisaType($visaType, $request->validated());

        return redirect()->route('admin.visa-types.edit', $visaType)
            ->with('success', 'Visa type created successfully.');
    }

    public function edit(VisaType $visaType): View
    {
        $this->authorize('access-admin-area');

        return view('admin.visa-types.edit', compact('visaType'));
    }

    public function update(UpdateVisaTypeRequest $request, VisaType $visaType): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->persistVisaType($visaType, $request->validated());

        return redirect()->route('admin.visa-types.edit', $visaType)
            ->with('success', 'Visa type updated successfully.');
    }

    public function destroy(VisaType $visaType): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $visaType->delete();

        return redirect()->route('admin.visa-types.index')
            ->with('success', 'Visa type deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistVisaType(VisaType $visaType, array $data): void
    {
        $visaType->fill([
            'name' => $data['name'],
            'processing_days' => $data['processing_days'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if (blank($visaType->slug) || $visaType->isDirty('name')) {
            $visaType->slug = $this->uniqueSlug((string) $data['name'], $visaType->id);
        }

        $visaType->save();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'visa-type';
        $candidate = $slug;
        $counter = 2;

        while (VisaType::query()->where('slug', $candidate)->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
