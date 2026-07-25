<?php
// طلب التحقق لتحديث حالة إرسال رسالة صادرة من الهاتف.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class SyncStatusSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_id' => 'required|integer',
            'device_id' => 'required|integer',
            'status' => 'required|string|in:sent,delivered,failed',
            'failure_reason' => 'nullable|string',
        ];
    }
}
