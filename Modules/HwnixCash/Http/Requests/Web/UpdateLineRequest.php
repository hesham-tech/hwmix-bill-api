<?php
// طلب التحقق من تعديل بيانات الخط وحدود المحافظ الإلكترونية باللوحة.

namespace Modules\HwnixCash\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'balance' => 'nullable|numeric|min:0',
            'actual_balance' => 'nullable|numeric|min:0',
            'daily_limit' => 'nullable|integer|min:0',
            'daily_withdraw_limit' => 'nullable|numeric|min:0',
            'daily_deposit_limit' => 'nullable|numeric|min:0',
            'monthly_withdraw_limit' => 'nullable|numeric|min:0',
            'monthly_deposit_limit' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ];
    }
}
