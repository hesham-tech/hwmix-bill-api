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

            // حقول إعدادات عتبة التنبيه
            'daily_withdraw_alert_type' => 'nullable|string|in:percentage,amount',
            'daily_withdraw_alert_value' => ['nullable', 'numeric', 'min:1', $this->validateAlertValueRule('daily_withdraw')],

            'daily_deposit_alert_type' => 'nullable|string|in:percentage,amount',
            'daily_deposit_alert_value' => ['nullable', 'numeric', 'min:1', $this->validateAlertValueRule('daily_deposit')],

            'monthly_withdraw_alert_type' => 'nullable|string|in:percentage,amount',
            'monthly_withdraw_alert_value' => ['nullable', 'numeric', 'min:1', $this->validateAlertValueRule('monthly_withdraw')],

            'monthly_deposit_alert_type' => 'nullable|string|in:percentage,amount',
            'monthly_deposit_alert_value' => ['nullable', 'numeric', 'min:1', $this->validateAlertValueRule('monthly_deposit')],

            'status' => 'sometimes|required|string|in:active,inactive,suspended',
            'note' => 'nullable|string',
        ];
    }

    private function validateAlertValueRule(string $limitKey): \Closure
    {
        return function ($attribute, $value, $fail) use ($limitKey) {
            if ($value === null || $value === '') {
                return;
            }

            $type = $this->input("{$limitKey}_alert_type", 'percentage');
            $limit = (float) $this->input("{$limitKey}_limit", 0);

            if ($type === 'percentage') {
                if ($value < 1 || $value > 100) {
                    $fail("قيمة تنبيه النسبة المئوية يجب أن تكون بين 1% و 100%.");
                }
            } elseif ($type === 'amount') {
                if ($value < 1) {
                    $fail("مبلغ التنبيه يجب أن يكون 1 ج.م على الأقل.");
                }
                if ($limit > 0 && $value > $limit) {
                    $fail("مبلغ التنبيه لا يمكن أن يتجاوز قيمة الحد نفسه ({$limit} ج.م).");
                }
            }
        };
    }
}
