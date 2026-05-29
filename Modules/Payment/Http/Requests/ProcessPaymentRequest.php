<?php

namespace Modules\Payment\Http\Requests;

// تعليق عربي: طلب التحقق من صحة المدخلات لمعالجة دفع فاتورة أو معاملة مالية.

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'payment_gateway_id' => 'required|exists:payment_gateways,id',
            'payable_type' => 'required|string',
            'payable_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'branch_id' => 'nullable|exists:branches,id',
            'options' => 'nullable|array',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ];
    }
}
