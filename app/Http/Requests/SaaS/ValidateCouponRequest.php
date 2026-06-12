<?php

namespace App\Http\Requests\SaaS;

use Illuminate\Foundation\Http\FormRequest;

//   طلب التحقق والتحصيل لمدخلات التحقق من صلاحية كوبونات وأكواد الخصم.
class ValidateCouponRequest extends FormRequest
{
    /**
     * تحديد صلاحية المستخدم لإجراء الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق من المدخلات.
     */
    public function rules(): array
    {
        return [
            'coupon_code' => 'required|string|max:50',
            'plan_id' => 'required|integer|exists:plans,id',
            'months' => 'required|integer|min:1|max:600',
        ];
    }

    /**
     * رسائل الخطأ المخصصة.
     */
    public function messages(): array
    {
        return [
            'coupon_code.required' => 'يرجى إدخال كود الخصم.',
            'plan_id.required' => 'يرجى تحديد الباقة.',
            'plan_id.exists' => 'الباقة المحددة غير موجودة.',
            'months.required' => 'يرجى تحديد عدد أشهر الاشتراك.',
        ];
    }
}
