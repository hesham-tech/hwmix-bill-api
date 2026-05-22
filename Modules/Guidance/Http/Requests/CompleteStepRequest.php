<?php

namespace Modules\Guidance\Http\Requests;

/**
 * كلاس التحقق من طلبات إكمال خطوة من الجولات الإرشادية.
 */

use Illuminate\Foundation\Http\FormRequest;

class CompleteStepRequest extends FormRequest
{
    /**
     * الحصول على قواعد التحقق المطبقة على الطلب.
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'skipped' => ['nullable', 'boolean'],
        ];
    }

    /**
     * تحديد ما إذا كان المستخدم مصرحًا له بإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        // مسموح لجميع المستخدمين المسجلين الدخول بإكمال خطواتهم الإرشادية
        return auth()->check();
    }
}

