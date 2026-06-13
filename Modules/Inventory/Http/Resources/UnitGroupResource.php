<?php
// كلاس تحويل بيانات مجموعة وحدات القياس للـ API
namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'type'       => $this->type,
            'company_id' => $this->company_id,
            'units'      => UnitResource::collection($this->whenLoaded('units')),
            'units_count' => $this->whenCounted('units'),
        ];
    }
}
