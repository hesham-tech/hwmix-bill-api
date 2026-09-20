<?php

namespace Modules\Store\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductDetailResource extends JsonResource
{
    /**
     * تحويل مورد المنتج لمصفوفة بيانات العرض التفصيلية
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'price' => (float)($this->variants->min('retail_price') ?? 0),
            'image' => $this->images->first()?->url ?? null,
            'images' => $this->images->map(fn($img) => ['id' => $img->id, 'url' => $img->url]),
            'vendor' => new VendorResource($this->whenLoaded('company')),
            'variants' => $this->whenLoaded('variants', function() {
                return $this->variants->map(function($variant) {
                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'price' => (float)$variant->price,
                        'available_stock' => $variant->available_stock,
                    ];
                });
            }),
            // Additional details can be added here
        ];
    }
}
