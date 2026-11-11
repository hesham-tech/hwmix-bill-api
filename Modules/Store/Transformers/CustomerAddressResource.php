<?php

namespace Modules\Store\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
{
    /**
     * تحويل مورد عنوان العميل لمصفوفة بيانات
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,'latitude'=>$this->latitude,'longitude'=>$this->longitude,'location_confirmed'=>$this->location_confirmed,
            'label' => $this->label,
            'recipient_name' => $this->recipient_name,
            'street' => $this->street,
            'district' => $this->district,
            'building' => $this->building,
            'floor' => $this->floor,
            'apartment' => $this->apartment,
            'landmark' => $this->landmark,
            'governorate_code' => $this->governorate_code,
            'city' => $this->city,
            'country' => $this->country,
            'is_default' => $this->is_default,
            'phone' => $this->phone,
        ];
    }
}
