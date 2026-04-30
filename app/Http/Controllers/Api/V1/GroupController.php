<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexGroupsApiRequest;
use App\Http\Resources\Api\V1\GroupResource;
use App\Models\TravelGroup;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class GroupController extends Controller
{
    public function index(IndexGroupsApiRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $groups = TravelGroup::query()
            ->with(['package.destination', 'package.category', 'images'])
            ->when(Arr::get($filters, 'q'), function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('group_type', 'like', "%{$q}%")
                        ->orWhereHas('package', fn ($pq) => $pq->where('title', 'like', "%{$q}%"));
                });
            })
            ->when(Arr::get($filters, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filters, 'departure_from'), fn ($query, $from) => $query->whereDate('departure_date', '>=', $from))
            ->when(Arr::get($filters, 'departure_to'), fn ($query, $to) => $query->whereDate('departure_date', '<=', $to))
            ->when(
                Arr::get($filters, 'sort') === 'earliest_departure',
                fn ($query) => $query->orderBy('departure_date')
            )
            ->when(
                Arr::get($filters, 'sort') === 'seats_left',
                fn ($query) => $query->orderByDesc('seats_left')
            )
            ->when(
                ! in_array(Arr::get($filters, 'sort'), ['earliest_departure', 'seats_left'], true),
                fn ($query) => $query->latest('id')
            )
            ->paginate($perPage);

        return GroupResource::collection($groups);
    }
}
