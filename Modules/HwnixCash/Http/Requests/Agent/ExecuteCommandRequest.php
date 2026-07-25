<?php
// طلب التحقق لتحديث نتيجة تنفيذ الأمر من طرف الهاتف.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|integer',
            'status' => 'required|string|in:executed,failed',
            'response_payload' => 'nullable|array',
        ];
    }
}
