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
            'driver' => 'required|string|in:stripe,paymob',
            'config' => 'required|array',
            'is_active' => 'nullable|boolean',
            'is_test_mode' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];
    }
}
