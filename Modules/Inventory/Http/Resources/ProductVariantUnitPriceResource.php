<?php
// كلاس يمثل متحور البيانات لأسعار وتكاليف الوحدات المخصصة لمتغير المنتج لتمريرها عبر الـ API
namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantUnitPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'unit_id' => $this->unit_id,
            'price' => (float) $this->price,
            'cost' => $this->cost !== null ? (float) $this->cost : null,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to' => $this->effective_to?->format('Y-m-d'),
            'is_default' => (bool) $this->is_default,
            'unit' => new UnitResource($this->whenLoaded('unit')),
        ];
    }
}
