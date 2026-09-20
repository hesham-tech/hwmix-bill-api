<?php

namespace Modules\Store\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreOrderResource extends JsonResource
{
    /**
     * تحويل مورد الطلب لمصفوفة بيانات
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'total_amount' => (float)$this->total_amount,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'shipping_address' => $this->shipping_address, // JSON
            'created_at' => $this->created_at,
            'sub_orders' => StoreSubOrderResource::collection($this->whenLoaded('subOrders')),
        ];
    }
}
