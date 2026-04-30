<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QuotationItem */
class QuotationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'title' => $this->title,
            'description' => $this->description,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
            'meta' => $this->meta,
            'reference' => $this->when(
                $this->reference_type !== null && $this->reference_id !== null,
                [
                    'type' => class_basename((string) $this->reference_type),
                    'id' => (int) $this->reference_id,
                ]
            ),
        ];
    }
}
