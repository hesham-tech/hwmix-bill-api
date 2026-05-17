<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'default_price' => $this->default_price,
            'period_unit' => $this->period_unit,
            'period_value' => $this->period_value,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'company' => $this->whenLoaded('company'),
            'creator' => $this->whenLoaded('creator'),
        ];
    }
}
