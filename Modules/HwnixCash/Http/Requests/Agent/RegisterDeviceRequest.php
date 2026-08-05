<?php
// طلب التحقق المخصص لتسجيل هاتف الأندرويد بالنظام.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'android_id' => 'required|string',
            'uuid' => 'nullable|string',
            'device_name' => 'nullable|string',
            'brand' => 'nullable|string',
            'model' => 'nullable|string',
            'android_version' => 'nullable|string',
            'app_version' => 'nullable|string',
            'capabilities' => 'nullable|array',
            'transfer_mode' => 'nullable|string|in:with_lines,device_only',
        ];
    }
}
