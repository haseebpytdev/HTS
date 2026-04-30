<?php

namespace App\Actions\Agency;

use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\QuotationRevisionRequest;

class RequestQuotationRevisionAction
{
    public function execute(Quotation $quotation, int $agencyId, int $userId, string $message): QuotationRevisionRequest
    {
        $revision = QuotationRevisionRequest::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agencyId,
            'user_id' => $userId,
            'message' => $message,
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        if ($quotation->inquiry_id) {
            Inquiry::query()
                ->whereKey($quotation->inquiry_id)
                ->update(['status' => Inquiry::STATUS_REVISION_REQUESTED]);
        }

        return $revision;
    }
}
