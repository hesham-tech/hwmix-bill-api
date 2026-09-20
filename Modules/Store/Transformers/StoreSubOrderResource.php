<?php

namespace Modules\Store\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreSubOrderResource extends JsonResource
{
    /**
     * تحويل مورد الطلب الفرعي لمصفوفة بيانات
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'store_order_id' => $this->store_order_id,
            'company_id' => $this->company_id,
            'status' => $this->status,
            'total_amount' => (float)$this->total_amount,
            'created_at' => $this->created_at,
            'vendor' => new VendorResource($this->whenLoaded('company')),
            'items' => $this->whenLoaded('items', function() {
                return $this->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'unit_price' => (float)$item->unit_price,
                        'subtotal' => (float)$item->subtotal,
                        'product' => [
                            'name' => $item->product_name,
                        ],
                        'variant' => [
                            'name' => $item->variant_name,
                        ]
                    ];
                });
            }),
            'order' => new StoreOrderResource($this->whenLoaded('order')),
        ];
    }
}
