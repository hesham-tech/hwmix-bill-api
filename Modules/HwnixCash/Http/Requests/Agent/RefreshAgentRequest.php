<?php
// طلب التحقق المخصص لتجديد توكن مصادقة تطبيق الأندرويد.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RefreshAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_uuid' => 'required|string',
        ];
    }
}
