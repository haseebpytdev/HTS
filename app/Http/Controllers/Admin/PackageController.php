<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterPackageRequest;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Models\Category;
use App\Models\Destination;
use App\Models\TravelPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(FilterPackageRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $packages = TravelPackage::query()
            ->with(['category:id,name', 'destination:id,name'])
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where('title', 'like', '%'.$q.'%'))
            ->when($filters['destination_id'] ?? null, fn ($query, int $destinationId) => $query->where('destination_id', $destinationId))
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when(array_key_exists('is_featured', $filters) && $filters['is_featured'] !== null, fn ($query) => $query->where('is_featured', (bool) $filters['is_featured']))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.packages.index', [
            'packages' => $packages,
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'destinations' => Destination::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.packages.create', $this->formData());
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $package = new TravelPackage();
        $this->persistPackage($package, $request->validated());

        return redirect()->route('admin.packages.edit', $package)
            ->with('success', 'Package created successfully.');
    }

    public function show(TravelPackage $package): View
    {
        $this->authorize('access-admin-area');
        $package->load(['category:id,name', 'destination:id,name']);

        return view('admin.packages.show', [
            'package' => $package,
            ...$this->extractContentSections((string) ($package->description ?? '')),
        ]);
    }

    public function edit(TravelPackage $package): View
    {
        $this->authorize('access-admin-area');

        return view('admin.packages.edit', array_merge($this->formData(), [
            'package' => $package,
            ...$this->extractContentSections((string) ($package->description ?? '')),
        ]));
    }

    public function update(UpdatePackageRequest $request, TravelPackage $package): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->persistPackage($package, $request->validated());

        return redirect()->route('admin.packages.edit', $package)
            ->with('success', 'Package updated successfully.');
    }

    public function destroy(TravelPackage $package): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $package->delete();

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistPackage(TravelPackage $package, array $data): void
    {
        $description = $this->composeDescription(
            overview: (string) ($data['overview'] ?? ''),
            itinerary: (string) ($data['itinerary'] ?? ''),
            inclusions: (string) ($data['inclusions'] ?? ''),
            exclusions: (string) ($data['exclusions'] ?? ''),
        );

        $package->fill([
            'title' => $data['title'],
            'destination_id' => $data['destination_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'excerpt' => $data['overview'] ?? null,
            'description' => $description,
            'duration_days' => $data['duration_days'] ?? null,
            'base_price' => $data['base_price'],
            'currency' => strtoupper((string) $data['currency']),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $providedSlug = trim((string) ($data['slug'] ?? ''));
        if ($providedSlug !== '') {
            $package->slug = $this->uniqueSlug($providedSlug, $package->id);
        } elseif (blank($package->slug) || $package->isDirty('title')) {
            $package->slug = $this->uniqueSlug((string) $data['title'], $package->id);
        }

        $package->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'destinations' => Destination::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        $slug = $base !== '' ? $base : 'package';
        $candidate = $slug;
        $counter = 2;

        while (TravelPackage::query()->where('slug', $candidate)->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function composeDescription(string $overview, string $itinerary, string $inclusions, string $exclusions): string
    {
        $sections = [
            '## Overview' => trim($overview),
            '## Itinerary' => trim($itinerary),
            '## Inclusions' => trim($inclusions),
            '## Exclusions' => trim($exclusions),
        ];

        $chunks = [];
        foreach ($sections as $heading => $body) {
            if ($body === '') {
                continue;
            }
            $chunks[] = $heading.PHP_EOL.$body;
        }

        return implode(PHP_EOL.PHP_EOL, $chunks);
    }

    /**
     * @return array{overview: string, itinerary: string, inclusions: string, exclusions: string}
     */
    private function extractContentSections(string $description): array
    {
        $out = [
            'overview' => '',
            'itinerary' => '',
            'inclusions' => '',
            'exclusions' => '',
        ];

        preg_match('/## Overview\s*(.*?)\s*(?=## Itinerary|## Inclusions|## Exclusions|$)/s', $description, $overview);
        preg_match('/## Itinerary\s*(.*?)\s*(?=## Overview|## Inclusions|## Exclusions|$)/s', $description, $itinerary);
        preg_match('/## Inclusions\s*(.*?)\s*(?=## Overview|## Itinerary|## Exclusions|$)/s', $description, $inclusions);
        preg_match('/## Exclusions\s*(.*?)\s*(?=## Overview|## Itinerary|## Inclusions|$)/s', $description, $exclusions);

        $out['overview'] = trim($overview[1] ?? '');
        $out['itinerary'] = trim($itinerary[1] ?? '');
        $out['inclusions'] = trim($inclusions[1] ?? '');
        $out['exclusions'] = trim($exclusions[1] ?? '');

        if ($out['overview'] === '' && $out['itinerary'] === '' && $out['inclusions'] === '' && $out['exclusions'] === '' && trim($description) !== '') {
            $out['itinerary'] = trim($description);
        }

        return $out;
    }
}
