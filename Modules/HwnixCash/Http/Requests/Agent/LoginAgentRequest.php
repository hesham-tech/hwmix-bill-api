<?php
// طلب التحقق المخصص لتسجيل دخول تطبيق الأندرويد.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class LoginAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required|string',
            'password' => 'required|string',
            'device_uuid' => 'required|string',
        ];
    }
}
