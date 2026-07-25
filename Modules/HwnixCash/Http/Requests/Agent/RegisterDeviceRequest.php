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
            'uuid' => 'required|string',
            'device_name' => 'required|string',
            'brand' => 'required|string',
            'model' => 'required|string',
            'android_version' => 'required|string',
            'app_version' => 'required|string',
            'capabilities' => 'nullable|array',
        ];
    }
}
