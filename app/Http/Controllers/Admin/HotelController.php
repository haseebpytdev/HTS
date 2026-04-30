<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterHotelRequest;
use App\Http\Requests\Admin\StoreHotelRequest;
use App\Http\Requests\Admin\UpdateHotelRequest;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HotelController extends Controller
{
    public function index(FilterHotelRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $hotels = Hotel::query()
            ->when($filters['q'] ?? null, function ($query, string $q): void {
                $query->where(function ($nested) use ($q): void {
                    $nested->where('name', 'like', '%'.$q.'%')
                        ->orWhere('city', 'like', '%'.$q.'%')
                        ->orWhere('address', 'like', '%'.$q.'%');
                });
            })
            ->when($filters['city'] ?? null, fn ($query, string $city) => $query->where('city', 'like', '%'.$city.'%'))
            ->when($filters['star_rating'] ?? null, fn ($query, int $rating) => $query->where('star_rating', $rating))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.hotels.index', [
            'hotels' => $hotels,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.hotels.create');
    }

    public function store(StoreHotelRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $hotel = new Hotel();
        $this->persistHotel($hotel, $request->validated());

        return redirect()->route('admin.hotels.edit', $hotel)
            ->with('success', 'Hotel created successfully.');
    }

    public function show(Hotel $hotel): View
    {
        $this->authorize('access-admin-area');

        return view('admin.hotels.show', compact('hotel'));
    }

    public function edit(Hotel $hotel): View
    {
        $this->authorize('access-admin-area');

        return view('admin.hotels.edit', compact('hotel'));
    }

    public function update(UpdateHotelRequest $request, Hotel $hotel): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $this->persistHotel($hotel, $request->validated());

        return redirect()->route('admin.hotels.edit', $hotel)
            ->with('success', 'Hotel updated successfully.');
    }

    public function destroy(Hotel $hotel): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $hotel->delete();

        return redirect()->route('admin.hotels.index')
            ->with('success', 'Hotel deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistHotel(Hotel $hotel, array $data): void
    {
        $hotel->fill([
            'name' => $data['name'],
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'star_rating' => $data['star_rating'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if (blank($hotel->slug) || $hotel->isDirty('name')) {
            $hotel->slug = $this->generateUniqueSlug((string) $data['name'], $hotel->id);
        }

        // Forward-compatible: persist only when schema has dedicated column.
        if (array_key_exists('distance_from_haram_km', $data) && Schema::hasColumn('hotels', 'distance_from_haram_km')) {
            $hotel->setAttribute('distance_from_haram_km', $data['distance_from_haram_km']);
        }

        $hotel->save();
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'hotel';
        $candidate = $slug;
        $index = 2;

        while (
            Hotel::query()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = $slug.'-'.$index;
            $index++;
        }

        return $candidate;
    }
}
