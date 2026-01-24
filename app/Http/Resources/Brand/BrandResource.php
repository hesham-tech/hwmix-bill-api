<?php

namespace App\Http\Resources\Brand;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'description' => $this->description,
            'active' => (bool) $this->active,
            'image_url' => $this->image?->url ? parse_url($this->image->url, PHP_URL_PATH) : null,
            'image_id' => $this->image?->id,
            'products_count' => $this->products_count ?? $this->products()->count(),
            'creator_name' => $this->creator?->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
