<?php

namespace Modules\Notification\Http\Requests;

//   طلب التحقق من صحة مدخلات قالب الإشعارات.

use Illuminate\Foundation\Http\FormRequest;

class NotificationTemplateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'channel' => 'required|string|in:whatsapp,email,both',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'is_active' => 'nullable|boolean',
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
