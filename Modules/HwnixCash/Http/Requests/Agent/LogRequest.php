<?php
// طلب التحقق لاستقبال سجلات التشخيص والتتبع من تطبيق الأندرويد.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class LogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|integer',
            'tag' => 'required|string',
            'message' => 'required|string',
            'details' => 'nullable|array',
        ];
    }
}
