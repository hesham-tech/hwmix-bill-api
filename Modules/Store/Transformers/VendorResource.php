<?php

namespace Modules\Store\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    /**
     * تحويل مورد الفيندور (الشركة) لمصفوفة بيانات العرض
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo' => $this->logo_url, // Assuming logo_url exists
            'store_name' => $this->store_name ?? $this->name,
            'store_description' => $this->store_description,
        ];
    }
}
