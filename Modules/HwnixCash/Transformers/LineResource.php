<?php
// محول استجابة بيانات خطوط ومحافظ كاش هونكس مع حساب مستهلكات ومتبقيات الحدود ديناميكياً.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_android_id' => $this->device_android_id,
            'slot_index' => $this->slot_index,
            'subscription_id' => $this->subscription_id,
            'carrier' => $this->carrier,
            'phone_number' => $this->phone_number,
            'balance' => (float) $this->balance,
            'actual_balance' => (float) $this->actual_balance,
            'daily_limit' => $this->daily_limit,
            
            // حدود المحافظ الإلكترونية المخزنة
            'daily_withdraw_limit' => $this->daily_withdraw_limit !== null ? (float) $this->daily_withdraw_limit : null,
            'daily_deposit_limit' => $this->daily_deposit_limit !== null ? (float) $this->daily_deposit_limit : null,
            'monthly_withdraw_limit' => $this->monthly_withdraw_limit !== null ? (float) $this->monthly_withdraw_limit : null,
            'monthly_deposit_limit' => $this->monthly_deposit_limit !== null ? (float) $this->monthly_deposit_limit : null,

            // المستهلكات المحسوبة ديناميكياً من واقع جدول المعاملات بدون تخزين
            'daily_withdraw_used' => $this->daily_withdraw_used,
            'daily_deposit_used' => $this->daily_deposit_used,
            'monthly_withdraw_used' => $this->monthly_withdraw_used,
            'monthly_deposit_used' => $this->monthly_deposit_used,

            // المتبقيات المحسوبة ديناميكياً
            'daily_withdraw_remaining' => $this->daily_withdraw_remaining,
            'daily_deposit_remaining' => $this->daily_deposit_remaining,
            'monthly_withdraw_remaining' => $this->monthly_withdraw_remaining,
            'monthly_deposit_remaining' => $this->monthly_deposit_remaining,

            'status' => $this->status,
            'note' => $this->note,
            'device_name' => $this->device?->device_name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
