<?php
// مورد تحويل بيانات الحساب المالي API Resource لكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'line_id' => $this->line_id,
            'line_phone_number' => $this->line?->phone_number,
            'line_carrier' => $this->line?->carrier,
            'message_source_id' => $this->message_source_id,
            'sender_identifier' => $this->messageSource?->sender_identifier,
            'name' => $this->name,
            'account_number' => $this->account_number,
            'balance' => (float) $this->balance,
            'actual_balance' => (float) $this->actual_balance,
            'balance_difference' => (float) $this->balance_difference,
            'has_balance_mismatch' => (bool) $this->has_balance_mismatch,
            'daily_withdraw_limit' => $this->daily_withdraw_limit !== null ? (float) $this->daily_withdraw_limit : null,
            'daily_deposit_limit' => $this->daily_deposit_limit !== null ? (float) $this->daily_deposit_limit : null,
            'monthly_withdraw_limit' => $this->monthly_withdraw_limit !== null ? (float) $this->monthly_withdraw_limit : null,
            'monthly_deposit_limit' => $this->monthly_deposit_limit !== null ? (float) $this->monthly_deposit_limit : null,
            'daily_withdraw_used' => (float) $this->daily_withdraw_used,
            'daily_deposit_used' => (float) $this->daily_deposit_used,
            'monthly_withdraw_used' => (float) $this->monthly_withdraw_used,
            'monthly_deposit_used' => (float) $this->monthly_deposit_used,
            'daily_withdraw_remaining' => $this->daily_withdraw_remaining,
            'daily_deposit_remaining' => $this->daily_deposit_remaining,
            'monthly_withdraw_remaining' => $this->monthly_withdraw_remaining,
            'monthly_deposit_remaining' => $this->monthly_deposit_remaining,
            'status' => $this->status,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
