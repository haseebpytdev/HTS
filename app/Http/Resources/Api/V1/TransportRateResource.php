<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TransportRate */
class TransportRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_name' => $this->vehicle_name,
            'route_from' => $this->route_from,
            'route_to' => $this->route_to,
            'trip_type' => $this->trip_type,
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'transport_type' => TransportTypeSummaryResource::make($this->whenLoaded('transportType')),
            'agency' => AgencySummaryResource::make($this->whenLoaded('agency')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
