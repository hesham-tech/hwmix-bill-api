<?php
// كلاس يمثل متحور البيانات لربط وحدة القياس بمتغير المنتج لتمريرها عبر الـ API
namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'unit_id' => $this->unit_id,
            'conversion_factor_to_base' => (float) $this->conversion_factor_to_base,
            'is_default' => (bool) $this->is_default,
            'min_quantity' => $this->min_quantity !== null ? (float) $this->min_quantity : null,
            'max_quantity' => $this->max_quantity !== null ? (float) $this->max_quantity : null,
            'allow_fraction' => (bool) $this->allow_fraction,
            'unit' => new UnitResource($this->whenLoaded('unit')),
        ];
    }
}
