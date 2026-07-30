<?php
// طلب التحقق من صحة مدخلات عملية تسوية الرصيد للحساب المالي.

namespace Modules\HwnixCash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconcileFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'سبب التسوية مطلوب ولا يمكن تنفيذه بدون سبب.',
            'reason.string' => 'سبب التسوية يجب أن يكون نصاً صالحاً.',
            'reason.max' => 'سبب التسوية يجب ألا يتجاوز 500 حرف.',
        ];
    }
}
