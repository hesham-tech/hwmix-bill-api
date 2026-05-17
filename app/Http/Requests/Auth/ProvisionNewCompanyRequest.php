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
            'phone' => 'required|string|unique:users,phone',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:8',
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
}
