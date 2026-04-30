<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterHotelRateRequest;
use App\Http\Requests\Admin\StoreHotelRateRequest;
use App\Http\Requests\Admin\UpdateHotelRateRequest;
use App\Models\Hotel;
use App\Models\HotelRate;
use App\Models\HotelRoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HotelRateController extends Controller
{
    public function index(FilterHotelRateRequest $request): View
    {
        $this->authorize('access-admin-area');
        $filters = $request->validated();

        $rates = HotelRate::query()
            ->with(['roomType:id,hotel_id,name', 'roomType.hotel:id,name'])
            ->when($filters['hotel_id'] ?? null, function ($query, int $hotelId): void {
                $query->whereHas('roomType', fn ($roomType) => $roomType->where('hotel_id', $hotelId));
            })
            ->when($filters['hotel_room_type_id'] ?? null, fn ($query, int $roomTypeId) => $query->where('hotel_room_type_id', $roomTypeId))
            ->when($filters['season_name'] ?? null, fn ($query, string $season) => $query->where('meal_plan', 'like', '%'.$season.'%'))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($filters['valid_from'] ?? null, fn ($query, string $from) => $query->whereDate('valid_from', '>=', $from))
            ->when($filters['valid_to'] ?? null, fn ($query, string $to) => $query->whereDate('valid_to', '<=', $to))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.hotel-rates.index', [
            'rates' => $rates,
            'filters' => $filters,
            'hotels' => Hotel::query()->orderBy('name')->get(['id', 'name']),
            'roomTypes' => HotelRoomType::query()->with('hotel:id,name')->orderBy('name')->get(['id', 'hotel_id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.hotel-rates.create', $this->formData());
    }

    public function store(StoreHotelRateRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $rate = new HotelRate();
        $this->persistRate($rate, $request->validated());

        return redirect()->route('admin.hotel-rates.edit', $rate)
            ->with('success', 'Hotel rate created successfully.');
    }

    public function show(HotelRate $hotelRate): View
    {
        $this->authorize('access-admin-area');
        $hotelRate->load(['roomType:id,hotel_id,name', 'roomType.hotel:id,name']);

        return view('admin.hotel-rates.show', compact('hotelRate'));
    }

    public function edit(HotelRate $hotelRate): View
    {
        $this->authorize('access-admin-area');

        return view('admin.hotel-rates.edit', array_merge($this->formData(), [
            'hotelRate' => $hotelRate,
        ]));
    }

    public function update(UpdateHotelRateRequest $request, HotelRate $hotelRate): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $this->persistRate($hotelRate, $request->validated());

        return redirect()->route('admin.hotel-rates.edit', $hotelRate)
            ->with('success', 'Hotel rate updated successfully.');
    }

    public function destroy(HotelRate $hotelRate): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $hotelRate->delete();

        return redirect()->route('admin.hotel-rates.index')
            ->with('success', 'Hotel rate deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistRate(HotelRate $rate, array $data): void
    {
        $rate->fill([
            'hotel_room_type_id' => $data['hotel_room_type_id'],
            'meal_plan' => $data['season_name'],
            'currency' => $data['currency'],
            'rate_per_night' => $data['rate_per_night'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'hotels' => Hotel::query()->orderBy('name')->get(['id', 'name']),
            'roomTypes' => HotelRoomType::query()->with('hotel:id,name')->orderBy('name')->get(['id', 'hotel_id', 'name']),
        ];
    }
}
