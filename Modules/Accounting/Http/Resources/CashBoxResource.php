<?php

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashBoxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'balance' => $this->balance,
            'cash_type' => $this->typeBox?->name,
            'cash_box_type_id' => $this->cash_box_type_id,
            'user_id' => $this->user_id,
            'created_by' => $this->created_by,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
