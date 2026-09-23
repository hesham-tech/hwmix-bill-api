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
            'description' => $this->desc,
            'desc_long' => $this->desc_long,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn() => ['id' => $this->category->id, 'name' => $this->category->name]),
            'brand' => $this->whenLoaded('brand', fn() => ['id' => $this->brand->id, 'name' => $this->brand->name]),
            'unit' => $this->whenLoaded('baseUnit', fn() => ['id' => $this->baseUnit->id, 'name' => $this->baseUnit->name]),
            'price' => (float)($this->variants->min('retail_price') ?? 0),
            'discount' => (float)($this->variants->max('discount') ?? 0),
            'old_price' => (float)($this->variants->min('retail_price') ?? 0) + (float)($this->variants->max('discount') ?? 0),
            'rating' => 0, // TODO: Implement reviews system
            'reviews_count' => 0, // TODO: Implement reviews system
            'image' => $this->images->first()?->url ?? null,
            'images' => $this->images->map(fn($img) => ['id' => $img->id, 'url' => $img->url]),
            'quantity' => $this->variants->sum(fn($v) => $v->stocks->sum(fn($s) => $s->quantity - $s->reserved)),
            'stock_status' => $this->variants->sum(fn($v) => $v->stocks->sum(fn($s) => $s->quantity - $s->reserved)) > 0 ? 'in_stock' : 'out_of_stock',
            'default_variant_id' => $this->variants->first()?->id ?? $this->id,
            'vendor' => new VendorResource($this->whenLoaded('company')),
            'variants' => $this->whenLoaded('variants', function() {
                return $this->variants->map(function($variant) {
                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'price' => (float)$variant->retail_price,
                        'barcode' => $variant->barcode,
                        'weight' => $variant->weight,
                        'dimensions' => $variant->dimensions,
                        'warranty_days' => $variant->warranty_days,
                        'image' => $variant->images->first()?->url ?? $variant->image ?? null,
                        'images' => $variant->images->map(fn($img) => ['id' => $img->id, 'url' => $img->url]),
                        'attributes' => $variant->relationLoaded('attributes') ? $variant->attributes->map(function($attr) {
                            return [
                                'name' => $attr->attribute->name ?? '',
                                'value' => $attr->attributeValue->name ?? $attr->value ?? '',
                            ];
                        }) : [],
                        'available_stock' => $variant->stocks->sum(fn($s) => $s->quantity - $s->reserved),
                    ];
                });
            }),
        ];
    }
}
