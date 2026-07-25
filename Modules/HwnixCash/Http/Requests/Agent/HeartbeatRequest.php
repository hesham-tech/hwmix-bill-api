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
            'device_id' => 'required|integer|exists:hwnix_cash_devices,id',
            'network_type' => 'nullable|string',
            'battery_level' => 'required|integer',
            'is_internet_available' => 'required|boolean',
            'free_memory_bytes' => 'nullable|integer',
            'free_storage_bytes' => 'nullable|integer',
            'app_version' => 'required|string',
            'configuration_version' => 'required|integer',
        ];
    }
}
