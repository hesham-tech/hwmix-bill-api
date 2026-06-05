<?php

namespace App\Http\Requests\SaaS;

use Illuminate\Foundation\Http\FormRequest;

// تعليق عربي: طلب التحقق والتحصيل لمدخلات حاسبة أسعار اشتراك باقات الساس.
class CalculatePricingRequest extends FormRequest
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
            'plan_id' => 'required|integer|exists:plans,id',
            'months' => 'required|integer|min:1|max:600', // حد أقصى 50 سنة
            'coupon_code' => 'nullable|string|max:50',
        ];
    }

    /**
     * رسائل الخطأ المخصصة.
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'يرجى تحديد الباقة المطلوبة.',
            'plan_id.exists' => 'الباقة المحددة غير موجودة.',
            'months.required' => 'يرجى تحديد عدد أشهر الاشتراك.',
            'months.integer' => 'يجب أن يكون عدد الأشهر رقماً صحيحاً.',
            'months.min' => 'يجب ألا يقل الاشتراك عن شهر واحد.',
        ];
    }
}
