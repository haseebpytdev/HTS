<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Quotation */
class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'quote_number' => $this->quote_number,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'travel_date' => $this->travel_date?->toDateString(),
            'return_date' => $this->return_date?->toDateString(),
            'adults' => (int) $this->adults,
            'children' => (int) $this->children,
            'infants' => (int) $this->infants,
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'promo_code' => $this->promo_code,
            'promo_discount_amount' => (float) ($this->promo_discount_amount ?? 0),
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'agency' => AgencySummaryResource::make($this->whenLoaded('agency')),
            'inquiry_id' => $this->inquiry_id,
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
