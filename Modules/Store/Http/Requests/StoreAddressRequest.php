<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرح له باتخاذ هذا الطلب
     */
    public function authorize()
    {
        return true;
    }

    /**
     * قواعد التحقق الخاصة بالطلب
     */
    public function rules()
    {
        return [
            'label' => 'nullable|string|max:50',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'country' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'district' => 'nullable|string|max:100',
            'street' => 'required|string|max:255',
            'building' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:255',
            'apartment' => 'nullable|string|max:255',
            'landmark' => 'nullable|string',
            'is_default' => 'boolean',
        ];
    }
}
