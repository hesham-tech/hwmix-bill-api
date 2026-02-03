<?php
namespace App\Http\Resources\InvoiceType;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type, // نوع الفاتورة (sale, purchase...)
            'description' => $this->description,
            'code' => $this->code,
            'context' => $this->context,
            'is_active' => $this->is_active ?? true, // من pivot أو افتراضي true
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
