<?php
// طلب التحقق لجلب الأوامر التشغيلية المعلقة للجهاز.

namespace Modules\HwnixCash\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class GetPendingCommandsRequest extends FormRequest
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
