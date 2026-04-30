<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterGroupRequest;
use App\Http\Requests\Admin\StoreGroupRequest;
use App\Http\Requests\Admin\UpdateGroupRequest;
use App\Models\Destination;
use App\Models\TravelGroup;
use App\Models\TravelPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(FilterGroupRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $groups = TravelGroup::query()
            ->with(['package:id,title,destination_id', 'package.destination:id,name'])
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where('name', 'like', '%'.$q.'%'))
            ->when($filters['destination_id'] ?? null, function ($query, int $destinationId): void {
                $query->whereHas('package', fn ($p) => $p->where('destination_id', $destinationId));
            })
            ->when($filters['package_id'] ?? null, fn ($query, int $packageId) => $query->where('package_id', $packageId))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, function ($query) use ($filters): void {
                if ((bool) $filters['is_active']) {
                    $query->where('status', '!=', 'closed');
                } else {
                    $query->where('status', 'closed');
                }
            })
            ->when(array_key_exists('is_featured', $filters) && $filters['is_featured'] !== null, function ($query) use ($filters): void {
                if ((bool) $filters['is_featured']) {
                    $query->where('status', 'featured');
                } else {
                    $query->where('status', '!=', 'featured');
                }
            })
            ->when($filters['departure_from'] ?? null, fn ($query, string $from) => $query->whereDate('departure_date', '>=', $from))
            ->when($filters['departure_to'] ?? null, fn ($query, string $to) => $query->whereDate('departure_date', '<=', $to))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.groups.index', [
            'groups' => $groups,
            'filters' => $filters,
            'packages' => TravelPackage::query()->orderBy('title')->get(['id', 'title', 'destination_id']),
            'destinations' => Destination::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.groups.create', [
            'packages' => TravelPackage::query()->with('destination:id,name')->orderBy('title')->get(['id', 'title', 'destination_id']),
        ]);
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $group = new TravelGroup();
        $this->persistGroup($group, $request->validated());

        return redirect()->route('admin.groups.edit', $group)
            ->with('success', 'Group created successfully.');
    }

    public function show(TravelGroup $group): View
    {
        $this->authorize('access-admin-area');
        $group->load(['package:id,title,destination_id', 'package.destination:id,name']);

        return view('admin.groups.show', [
            'group' => $group,
            ...$this->extractNotes((string) ($group->notes ?? '')),
        ]);
    }

    public function edit(TravelGroup $group): View
    {
        $this->authorize('access-admin-area');

        return view('admin.groups.edit', [
            'group' => $group,
            'packages' => TravelPackage::query()->with('destination:id,name')->orderBy('title')->get(['id', 'title', 'destination_id']),
            ...$this->extractNotes((string) ($group->notes ?? '')),
        ]);
    }

    public function update(UpdateGroupRequest $request, TravelGroup $group): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->persistGroup($group, $request->validated());

        return redirect()->route('admin.groups.edit', $group)
            ->with('success', 'Group updated successfully.');
    }

    public function destroy(TravelGroup $group): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $group->delete();

        return redirect()->route('admin.groups.index')
            ->with('success', 'Group deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistGroup(TravelGroup $group, array $data): void
    {
        $isFeatured = (bool) ($data['is_featured'] ?? false);
        $isActive = (bool) ($data['is_active'] ?? true);
        $status = $isFeatured ? 'featured' : ($isActive ? 'open' : 'closed');

        $group->fill([
            'package_id' => $data['package_id'],
            'name' => $data['name'],
            'group_type' => $data['pricing_tiers'] ?? null,
            'departure_date' => $data['departure_date'] ?? null,
            'return_date' => $data['return_date'] ?? null,
            'capacity' => $data['capacity'],
            'seats_left' => $data['seats_left'],
            'status' => $status,
            'notes' => $this->composeNotes(
                pricingTiers: (string) ($data['pricing_tiers'] ?? ''),
                airlineInfo: (string) ($data['airline_info'] ?? ''),
                hotelInfo: (string) ($data['hotel_info'] ?? ''),
                notes: (string) ($data['notes'] ?? ''),
            ),
        ]);

        if (blank($group->slug) || $group->isDirty('name')) {
            $group->slug = $this->uniqueSlug((string) $data['name'], $group->id);
        }

        $group->save();
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        $slug = $base !== '' ? $base : 'group';
        $candidate = $slug;
        $counter = 2;

        while (TravelGroup::query()->where('slug', $candidate)->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function composeNotes(string $pricingTiers, string $airlineInfo, string $hotelInfo, string $notes): string
    {
        $sections = [
            '## Pricing Tiers' => trim($pricingTiers),
            '## Airline Info' => trim($airlineInfo),
            '## Hotel Info' => trim($hotelInfo),
            '## Notes' => trim($notes),
        ];

        $chunks = [];
        foreach ($sections as $heading => $body) {
            if ($body !== '') {
                $chunks[] = $heading.PHP_EOL.$body;
            }
        }

        return implode(PHP_EOL.PHP_EOL, $chunks);
    }

    /**
     * @return array{pricing_tiers: string, airline_info: string, hotel_info: string, notes_text: string}
     */
    private function extractNotes(string $notes): array
    {
        $out = [
            'pricing_tiers' => '',
            'airline_info' => '',
            'hotel_info' => '',
            'notes_text' => '',
        ];

        preg_match('/## Pricing Tiers\s*(.*?)\s*(?=## Airline Info|## Hotel Info|## Notes|$)/s', $notes, $pricing);
        preg_match('/## Airline Info\s*(.*?)\s*(?=## Pricing Tiers|## Hotel Info|## Notes|$)/s', $notes, $airline);
        preg_match('/## Hotel Info\s*(.*?)\s*(?=## Pricing Tiers|## Airline Info|## Notes|$)/s', $notes, $hotel);
        preg_match('/## Notes\s*(.*?)\s*(?=## Pricing Tiers|## Airline Info|## Hotel Info|$)/s', $notes, $misc);

        $out['pricing_tiers'] = trim($pricing[1] ?? '');
        $out['airline_info'] = trim($airline[1] ?? '');
        $out['hotel_info'] = trim($hotel[1] ?? '');
        $out['notes_text'] = trim($misc[1] ?? '');

        if ($out['pricing_tiers'] === '' && $out['airline_info'] === '' && $out['hotel_info'] === '' && $out['notes_text'] === '' && trim($notes) !== '') {
            $out['notes_text'] = trim($notes);
        }

        return $out;
    }
}
