<?php
// طلب التحقق للمزامنة الجماعية للرسائل الواردة المتراكمة بالهاتف.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class BatchSyncSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|integer',
            'messages' => 'required|array',
            'messages.*.subscription_id' => 'required|string',
            'messages.*.phone_number' => 'required|string',
            'messages.*.message_body' => 'nullable|string',
            'messages.*.message_ref' => 'required|string',
            'messages.*.contact_name' => 'nullable|string',
            'messages.*.sent_at' => 'nullable|string',
        ];
    }
}
