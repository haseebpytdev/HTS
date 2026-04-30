<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\UpsertQuotationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQuotationApiRequest;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;

class QuotationController extends Controller
{
    public function __construct(
        private readonly UpsertQuotationAction $upsertQuotationAction
    ) {
    }

    public function show(Quotation $quotation): QuotationResource
    {
        $quotation->load(['agency', 'items']);

        return new QuotationResource($quotation);
    }

    public function store(StoreQuotationApiRequest $request): JsonResponse
    {
        $quotation = $this->upsertQuotationAction->execute($request->validated());

        return (new QuotationResource($quotation))
            ->response()
            ->setStatusCode(201);
    }
}
