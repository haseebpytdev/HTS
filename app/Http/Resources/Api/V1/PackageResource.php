<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TravelPackage */
class PackageResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'duration_days' => $this->duration_days,
            'base_price' => (float) $this->base_price,
            'currency' => $this->currency,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'cover_image_url' => $cover ? Media::url($cover->image_path) : null,
            'destination' => DestinationResource::make($this->whenLoaded('destination')),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
