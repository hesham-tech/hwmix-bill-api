<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserBasicResource;

use App\Http\Resources\Company\CompanyResource;

class WarehouseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'manager' => $this->manager,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'is_default' => $this->is_default,
            'description' => $this->description,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'stocks' => $this->whenLoaded('stocks', fn() => StockResource::collection($this->stocks)),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
