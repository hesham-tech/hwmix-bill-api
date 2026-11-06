<?php

namespace Modules\Payment\Http\Requests;

//   طلب التحقق من صحة المدخلات لإضافة وتحديث بوابات الدفع الإلكتروني.

use Illuminate\Foundation\Http\FormRequest;

class PaymentGatewayRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'driver' => ['required', \Illuminate\Validation\Rule::in(array_keys(\Modules\Payment\Services\GatewayRegistry::definitions()))],
            'config' => 'present|array',
            'config.*' => 'nullable|string|max:4096',
            'is_active' => 'nullable|boolean',
            'is_test_mode' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];
    }
}
