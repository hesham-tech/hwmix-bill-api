<?php

namespace App\Http\Resources\PaymentMethod;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'active' => (bool) $this->active,
            'is_active' => (bool) $this->active, // For compatibility
            'is_system' => (bool) $this->is_system,
            'company_id' => $this->company_id,
            'image_id' => $this->image?->id,
            'image_url' => $this->image?->url ? (str_starts_with($this->image->url, 'http') ? $this->image->url : url($this->image->url)) : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
