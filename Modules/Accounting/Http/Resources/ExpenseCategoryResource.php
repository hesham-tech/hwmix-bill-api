<?php

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseCategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'code' => $this->code,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
