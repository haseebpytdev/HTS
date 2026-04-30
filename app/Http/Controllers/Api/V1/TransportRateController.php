<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexTransportRatesApiRequest;
use App\Http\Resources\Api\V1\TransportRateResource;
use App\Models\TransportRate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class TransportRateController extends Controller
{
    public function index(IndexTransportRatesApiRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 50);

        $rates = TransportRate::query()
            ->with(['transportType', 'agency'])
            ->where('is_active', true)
            ->when(Arr::get($filters, 'agency_id'), fn ($q, $id) => $q->where('agency_id', $id))
            ->orderBy('id')
            ->paginate($perPage);

        return TransportRateResource::collection($rates);
    }
}
