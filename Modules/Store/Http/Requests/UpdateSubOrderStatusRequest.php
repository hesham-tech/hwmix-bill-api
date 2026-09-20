<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubOrderStatusRequest extends FormRequest
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
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
        ];
    }
}
