<?php
// كلاس تحويل بيانات قواعد تحويل وحدات القياس للـ API
namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitConversionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'unit_group_id'  => $this->unit_group_id,
            'from_unit_id'   => $this->from_unit_id,
            'to_unit_id'     => $this->to_unit_id,
            'factor'         => (float) $this->factor,
            'reverse_factor' => (float) $this->reverse_factor,
            'company_id'     => $this->company_id,
            'from_unit'      => new UnitResource($this->whenLoaded('fromUnit')),
            'to_unit'        => new UnitResource($this->whenLoaded('toUnit')),
            'group'          => new UnitGroupResource($this->whenLoaded('group')),
        ];
    }
}
