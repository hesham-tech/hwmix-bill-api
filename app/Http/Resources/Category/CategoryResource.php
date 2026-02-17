<?php

namespace App\Http\Resources\Category;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'description' => $this->description,
            'active' => (bool) $this->active,
            'image_url' => $this->image?->url,
            'image_id' => $this->image?->id,
            'parent_id' => $this->parent_id,
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'children_count' => $this->children_count ?? $this->children()->count(),
            'products_count' => $this->products_count ?? $this->products()->count(),
            'synonyms' => $this->synonyms,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
