<?php
// طلب التحقق لجلب قائمة الخطوط المسجلة للجهاز.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class GetLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|integer|exists:hwnix_cash_devices,id',
        ];
    }
}
