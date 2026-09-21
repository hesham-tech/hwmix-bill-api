<?php

namespace Modules\Store\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    /**
     * تحويل مورد المنتج لمصفوفة بيانات العرض المختصرة
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
            'quantity' => $this->variants->sum(fn($v) => $v->stocks->sum(fn($s) => $s->quantity - $s->reserved)),
            'stock_status' => $this->variants->sum(fn($v) => $v->stocks->sum(fn($s) => $s->quantity - $s->reserved)) > 0 ? 'in_stock' : 'out_of_stock',
            'default_variant_id' => $this->variants->first()?->id ?? $this->id,
            'vendor' => new VendorResource($this->whenLoaded('company')),
            'variants' => $this->whenLoaded('variants', function() {
                return $this->variants->map(function($variant) {
                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'price' => (float)$variant->retail_price,
                        'available_stock' => $variant->stocks->sum(fn($s) => $s->quantity - $s->reserved),
                    ];
                });
            }),
        ];
    }
}
