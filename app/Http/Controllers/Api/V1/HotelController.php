<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexHotelsApiRequest;
use App\Http\Resources\Api\V1\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class HotelController extends Controller
{
    public function index(IndexHotelsApiRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 30);

        $hotels = Hotel::query()
            ->with('agency')
            ->where('is_active', true)
            ->when(Arr::get($filters, 'agency_id'), fn ($q, $id) => $q->where('agency_id', $id))
            ->when(Arr::get($filters, 'q'), function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%")
                        ->orWhere('country', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        return HotelResource::collection($hotels);
    }
}
