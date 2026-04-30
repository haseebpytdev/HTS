<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Inquiry\CreateLeadInquiryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInquiryApiRequest;
use App\Http\Resources\Api\V1\InquiryResource;
use Illuminate\Http\JsonResponse;

class InquiryController extends Controller
{
    public function __construct(
        private readonly CreateLeadInquiryAction $createLeadInquiryAction
    ) {
    }

    public function store(StoreInquiryApiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $source = $data['source'];
        $inquiry = $this->createLeadInquiryAction->execute($data, $source);

        return (new InquiryResource($inquiry))
            ->response()
            ->setStatusCode(201);
    }
}
