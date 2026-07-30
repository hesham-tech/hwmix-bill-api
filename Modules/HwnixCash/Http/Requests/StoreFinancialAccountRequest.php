<?php
// فحص وتدقيق طلب إنشاء حساب مالي جديد بكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'line_id' => 'required|exists:hwnix_cash_lines,id',
            'sender_identifier' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'daily_withdraw_limit' => 'nullable|numeric|min:0',
            'daily_deposit_limit' => 'nullable|numeric|min:0',
            'monthly_withdraw_limit' => 'nullable|numeric|min:0',
            'monthly_deposit_limit' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ];
    }
}
