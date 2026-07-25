<?php
// طلب التحقق المخصص لإرسال وجدولة رسالة جديدة من لوحة تحكم الويب.

namespace Modules\HwnixCash\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sms_line_id' => 'required|integer',
            'phone_number' => 'required|string',
            'message_body' => 'required|string',
        ];
    }
}
