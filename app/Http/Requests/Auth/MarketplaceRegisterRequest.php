<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MarketplaceRegisterRequest extends FormRequest
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
            'full_name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'phone' => ['required','string','regex:/^(?:\\+?20|0)1[0125][0-9]{8}$/','unique:users,phone'],
            'email' => 'required|email|max:255|unique:users,email',
            'legal_accepted' => 'required|accepted',
            'legal_version_ids' => 'required|array|min:1',
            'legal_version_ids.*' => 'required|integer|distinct',
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم بالكامل مطلوب.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
        ];
    }
}
