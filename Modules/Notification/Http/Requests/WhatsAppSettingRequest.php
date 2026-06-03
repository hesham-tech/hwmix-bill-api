<?php

namespace Modules\Notification\Http\Requests;

// تعليق عربي: طلب التحقق من صحة مدخلات إعدادات الواتساب (Meta Cloud API).

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppSettingRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'phone_number_id' => 'required|string|max:255',
            'waba_id' => 'required|string|max:255',
            'access_token' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'is_global' => [
                'nullable',
                'boolean',
                function ($attribute, $value, $fail) {
                    if ($value && (!auth()->user() || !auth()->user()->hasPermissionTo(perm_key('admin.super')))) {
                        $fail('غير مسموح لغير المسؤولين الخارقين تفعيل الإعدادات العامة.');
                    }
                }
            ],
        ];
    }
}
