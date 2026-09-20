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
            'title' => 'required|string|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'country' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'is_default' => 'boolean',
        ];
    }
}
