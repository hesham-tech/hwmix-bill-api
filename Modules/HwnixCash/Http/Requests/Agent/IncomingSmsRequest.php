<?php
// طلب التحقق لاستقبال رسالة واردة جديدة من الهاتف.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class IncomingSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|integer',
            'subscription_id' => 'required|string',
            'phone_number' => 'required|string',
            'message_body' => 'required|string',
            'message_ref' => 'required|string',
            'sent_at' => 'nullable|string',
        ];
    }
}
