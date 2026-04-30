<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexVisaRatesApiRequest;
use App\Http\Resources\Api\V1\VisaRateResource;
use App\Models\VisaRate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class VisaRateController extends Controller
{
    public function index(IndexVisaRatesApiRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 50);

        $rates = VisaRate::query()
            ->with(['visaType', 'agency'])
            ->where('is_active', true)
            ->when(Arr::get($filters, 'agency_id'), fn ($q, $id) => $q->where('agency_id', $id))
            ->orderBy('id')
            ->paginate($perPage);

        return VisaRateResource::collection($rates);
    }
}
