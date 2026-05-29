<?php

namespace Modules\Notification\Http\Requests;

// تعليق عربي: طلب التحقق من صحة مدخلات إعدادات البريد الإلكتروني للشركة.

use Illuminate\Foundation\Http\FormRequest;

class MailSettingRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'mail_transport' => 'required|string|in:smtp,mailgun,ses',
            'mail_host' => 'required_if:mail_transport,smtp|string|nullable',
            'mail_port' => 'required_if:mail_transport,smtp|integer|nullable',
            'mail_username' => 'required_if:mail_transport,smtp|string|nullable',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string|in:tls,ssl',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];
    }
}
