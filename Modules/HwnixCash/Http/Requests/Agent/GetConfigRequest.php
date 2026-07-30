<?php
// طلب التحقق لجلب تكوين وإعدادات الهاتف.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class GetConfigRequest extends FormRequest
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
