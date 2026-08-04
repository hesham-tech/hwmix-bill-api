<?php
// طلب التحقق المخصص لنبضات تشغيل وصحة الهاتف.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|integer',
            'network_type' => 'nullable|string',
            'battery_level' => 'nullable|integer',
            'is_internet_available' => 'nullable|boolean',
            'free_memory_bytes' => 'nullable|integer',
            'free_storage_bytes' => 'nullable|integer',
            'app_version' => 'nullable|string',
            'configuration_version' => 'nullable|integer',
        ];
    }
}
