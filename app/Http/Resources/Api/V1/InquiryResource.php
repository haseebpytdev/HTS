<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\LeadPipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Inquiry */
class InquiryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'source' => $this->source,
            'status' => $this->status,
            'pipeline_stage' => $this->pipeline_stage instanceof LeadPipelineStage ? $this->pipeline_stage->value : $this->pipeline_stage,
            'estimated_value' => $this->estimated_value !== null ? (float) $this->estimated_value : null,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'travel_date' => $this->travel_date?->toDateString(),
            'adults' => (int) $this->adults,
            'children' => (int) $this->children,
            'budget' => $this->budget !== null ? (float) $this->budget : null,
            'currency' => $this->currency,
            'message' => $this->message,
            'package_id' => $this->package_id,
            'group_id' => $this->group_id,
            'destination_id' => $this->destination_id,
            'agency_id' => $this->agency_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
