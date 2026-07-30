<?php
// طلب التحقق المخصص لمزامنة شرائح الـ SIM المتاحة بالهاتف.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class SyncLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|integer',
            'device_name' => 'nullable|string',
            'sims' => 'required|array',
            'sims.*.slot_index' => 'required|integer',
            'sims.*.subscription_id' => 'required|string',
            'sims.*.carrier' => 'nullable|string',
            'sims.*.mcc' => 'nullable|string',
            'sims.*.mnc' => 'nullable|string',
            'sims.*.phone_number' => 'nullable|string',
            'sims.*.network_type' => 'nullable|string',
            'sims.*.signal_strength' => 'nullable|integer',
        ];
    }
}
