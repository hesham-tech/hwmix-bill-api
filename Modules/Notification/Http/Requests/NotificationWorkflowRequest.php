<?php

namespace Modules\Notification\Http\Requests;

// تعليق عربي: طلب التحقق من صحة مدخلات قاعدة أتمتة الإشعارات والخطوات التابعة لها.

use Illuminate\Foundation\Http\FormRequest;

class NotificationWorkflowRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'event_type' => 'required|string|max:255',
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
            'steps' => 'required|array|min:1',
            'steps.*.id' => 'nullable|integer',
            'steps.*.step_number' => 'required|integer|min:1',
            'steps.*.delay_days' => 'required|integer',
            'steps.*.channel' => 'required|string|in:whatsapp,email,both',
            'steps.*.template_id' => 'required|integer|exists:notification_templates,id',
            'steps.*.is_active' => 'nullable|boolean',
        ];
    }
}
