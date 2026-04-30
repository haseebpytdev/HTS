<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TravelGroup */
class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_cover', true) ?? $this->images->first())
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'group_type' => $this->group_type,
            'departure_date' => $this->departure_date?->toDateString(),
            'return_date' => $this->return_date?->toDateString(),
            'capacity' => $this->capacity,
            'seats_left' => $this->seats_left,
            'status' => $this->status,
            'notes' => $this->notes,
            'cover_image_url' => $cover ? Media::url($cover->image_path) : null,
            'package' => PackageResource::make($this->whenLoaded('package')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
