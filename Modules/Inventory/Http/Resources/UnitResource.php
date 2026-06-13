<?php
// كلاس يمثل متحور البيانات لوحدة القياس لتمريرها عبر الـ API
namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_group_id' => $this->unit_group_id,
            'name' => $this->name,
            'code' => $this->code,
            'decimal_places' => (int) $this->decimal_places,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
