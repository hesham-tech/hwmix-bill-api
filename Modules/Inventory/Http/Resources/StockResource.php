<?php

namespace Modules\Inventory\Http\Resources;

use App\Http\Resources\Company\CompanyResource;
use App\Http\Resources\User\UserBasicResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'quantity' => $this->quantity,
            'reserved' => $this->reserved,
            'min_quantity' => $this->min_quantity,
            'cost' => $this->cost,
            'batch' => $this->batch,
            'expiry' => $this->expiry,
            'loc' => $this->loc,
            'status' => $this->status,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'updater' => new UserBasicResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
