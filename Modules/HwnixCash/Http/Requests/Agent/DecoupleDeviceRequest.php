<?php
// طلب التحقق لفك ربط الجهاز من تطبيق الأندرويد.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class DecoupleDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|integer',
        ];
    }
}
