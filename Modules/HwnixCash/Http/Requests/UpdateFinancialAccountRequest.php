<?php
// فحص وتدقيق طلب تعديل بيانات حساب مالي بكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'daily_withdraw_limit' => 'nullable|numeric|min:0',
            'daily_deposit_limit' => 'nullable|numeric|min:0',
            'monthly_withdraw_limit' => 'nullable|numeric|min:0',
            'monthly_deposit_limit' => 'nullable|numeric|min:0',
            'status' => 'sometimes|required|string|in:active,inactive,suspended',
            'note' => 'nullable|string',
        ];
    }
}
