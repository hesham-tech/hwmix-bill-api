<?php

namespace App\Http\Resources\InvoiceItem;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductVariant\ProductVariantResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'invoice_id' => $this->invoice_id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'profit_margin' => $this->profit_margin,
            'total' => $this->total,
            'primary_image_url' => $this->variant?->image ? asset($this->variant->image->url) : ($this->product && $this->product->image ? asset($this->product->image->url) : null),
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
            'digital_deliveries' => $this->whenLoaded('digitalDeliveries'),

            'product' => $this->whenLoaded('product'),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
