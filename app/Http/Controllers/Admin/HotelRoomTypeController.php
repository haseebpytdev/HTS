<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterHotelRoomTypeRequest;
use App\Http\Requests\Admin\StoreHotelRoomTypeRequest;
use App\Http\Requests\Admin\UpdateHotelRoomTypeRequest;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HotelRoomTypeController extends Controller
{
    public function index(FilterHotelRoomTypeRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $roomTypes = HotelRoomType::query()
            ->with('hotel:id,name')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where('name', 'like', '%'.$q.'%'))
            ->when($filters['hotel_id'] ?? null, fn ($query, int $hotelId) => $query->where('hotel_id', $hotelId))
            ->when($filters['sharing_basis'] ?? null, function ($query, string $basis): void {
                $query->where('base_capacity', $this->sharingBasisToCapacity($basis));
            })
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.hotel-room-types.index', [
            'roomTypes' => $roomTypes,
            'filters' => $filters,
            'hotels' => Hotel::query()->orderBy('name')->get(['id', 'name']),
            'sharingOptions' => $this->sharingOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.hotel-room-types.create', [
            'hotels' => Hotel::query()->orderBy('name')->get(['id', 'name']),
            'sharingOptions' => $this->sharingOptions(),
        ]);
    }

    public function store(StoreHotelRoomTypeRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $roomType = new HotelRoomType();
        $this->persistRoomType($roomType, $request->validated());

        return redirect()->route('admin.hotel-room-types.edit', $roomType)
            ->with('success', 'Room type created successfully.');
    }

    public function show(HotelRoomType $hotelRoomType): View
    {
        $this->authorize('access-admin-area');
        $hotelRoomType->load('hotel:id,name');

        return view('admin.hotel-room-types.show', [
            'roomType' => $hotelRoomType,
            'sharingLabel' => $this->capacityToSharingBasis((int) $hotelRoomType->base_capacity),
        ]);
    }

    public function edit(HotelRoomType $hotelRoomType): View
    {
        $this->authorize('access-admin-area');

        return view('admin.hotel-room-types.edit', [
            'roomType' => $hotelRoomType,
            'hotels' => Hotel::query()->orderBy('name')->get(['id', 'name']),
            'sharingOptions' => $this->sharingOptions(),
        ]);
    }

    public function update(UpdateHotelRoomTypeRequest $request, HotelRoomType $hotelRoomType): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->persistRoomType($hotelRoomType, $request->validated());

        return redirect()->route('admin.hotel-room-types.edit', $hotelRoomType)
            ->with('success', 'Room type updated successfully.');
    }

    public function destroy(HotelRoomType $hotelRoomType): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $hotelRoomType->delete();

        return redirect()->route('admin.hotel-room-types.index')
            ->with('success', 'Room type deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistRoomType(HotelRoomType $roomType, array $data): void
    {
        $capacity = $this->sharingBasisToCapacity((string) $data['sharing_basis']);

        $roomType->fill([
            'hotel_id' => $data['hotel_id'],
            'name' => $data['name'],
            'base_capacity' => $capacity,
            'max_adults' => $capacity,
            'max_children' => (int) ($data['max_children'] ?? 0),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();
    }

    /**
     * @return array<string, int>
     */
    private function sharingOptions(): array
    {
        return [
            'single' => 1,
            'double' => 2,
            'triple' => 3,
            'quad' => 4,
        ];
    }

    private function sharingBasisToCapacity(string $basis): int
    {
        return $this->sharingOptions()[$basis] ?? 2;
    }

    private function capacityToSharingBasis(int $capacity): string
    {
        $map = array_flip($this->sharingOptions());

        return $map[$capacity] ?? 'double';
    }
}
