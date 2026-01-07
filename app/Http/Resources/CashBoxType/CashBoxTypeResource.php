<?php

namespace App\Http\Resources\CashBoxType;

use Illuminate\Http\Resources\Json\JsonResource;

class CashBoxTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_default' => (bool) $this->is_default,
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
