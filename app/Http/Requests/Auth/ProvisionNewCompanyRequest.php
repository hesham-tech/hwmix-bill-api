<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ProvisionNewCompanyRequest extends FormRequest
{
    /**
     * @group 01. إدارة المصادقة
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // بيانات الشركة
            'company_name' => 'required|string|max:255',
            'company_phone' => 'nullable|string|max:20',
            'company_email' => 'nullable|email',
            'address' => 'nullable|string|max:500',

            // بيانات المالك
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'password' => 'required|string|min:8',
            'plan_id' => 'nullable|exists:plans,id',
            'months' => 'nullable|integer|min:1',
            'coupon_code' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'اسم الشركة مطلوب.',
            'full_name.required' => 'اسم المالك مطلوب.',
            'phone.required' => 'رقم هاتف المالك مطلوب.',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty()) {
                $phone = $this->input('phone');
                $email = $this->input('email');

                $userByPhone = \App\Models\User::withoutGlobalScopes()->where('phone', $phone)->first();
                $userByEmail = $email ? \App\Models\User::withoutGlobalScopes()->where('email', $email)->first() : null;

                if ($userByPhone && $userByEmail && $userByPhone->id !== $userByEmail->id) {
                    $validator->errors()->add('email', 'البريد الإلكتروني مرتبط بحساب آخر غير مرتبط برقم الهاتف.');
                    return;
                }

                $user = $userByPhone ?? $userByEmail;

                if ($user) {
                    if (!\Illuminate\Support\Facades\Hash::check($this->input('password'), $user->password)) {
                        $validator->errors()->add('password', 'هذا الحساب مسجل مسبقاً. يرجى إدخال كلمة المرور الصحيحة لإنشاء شركة جديدة تحته.');
                    }
                }
            }
        });
    }
}
