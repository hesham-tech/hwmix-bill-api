<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'code' => $this->code,
            'context' => $this->context,
            'is_active' => $this->is_active ?? true,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
