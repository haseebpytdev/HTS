<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexPackagesApiRequest;
use App\Http\Resources\Api\V1\PackageResource;
use App\Models\TravelPackage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class PackageController extends Controller
{
    public function index(IndexPackagesApiRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $packages = TravelPackage::query()
            ->with(['destination', 'category', 'images'])
            ->where('is_active', true)
            ->when(Arr::get($filters, 'q'), function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('excerpt', 'like', "%{$q}%");
                });
            })
            ->when(Arr::get($filters, 'destination'), function ($query, $destination) {
                $query->whereHas('destination', function ($inner) use ($destination) {
                    $inner->where('slug', $destination)->orWhere('name', 'like', "%{$destination}%");
                });
            })
            ->when(Arr::get($filters, 'category'), function ($query, $category) {
                $query->whereHas('category', function ($inner) use ($category) {
                    $inner->where('slug', $category)->orWhere('name', 'like', "%{$category}%");
                });
            })
            ->when(Arr::get($filters, 'price_min'), fn ($query, $min) => $query->where('base_price', '>=', $min))
            ->when(Arr::get($filters, 'price_max'), fn ($query, $max) => $query->where('base_price', '<=', $max))
            ->when(
                Arr::get($filters, 'sort') === 'price_low',
                fn ($query) => $query->orderBy('base_price')
            )
            ->when(
                Arr::get($filters, 'sort') === 'price_high',
                fn ($query) => $query->orderByDesc('base_price')
            )
            ->when(
                ! in_array(Arr::get($filters, 'sort'), ['price_low', 'price_high'], true),
                fn ($query) => $query->latest('id')
            )
            ->paginate($perPage);

        return PackageResource::collection($packages);
    }
}
