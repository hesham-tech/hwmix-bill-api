<?php
// محول استجابة بيانات خطوط ومحافظ كاش هونكس مع حساب مستهلكات ومتبقيات الحدود ديناميكياً.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $balance = (float) $this->balance;
        $actualBalance = (float) $this->actual_balance;
        $balanceDifference = round($actualBalance - $balance, 2);

        return [
            'id' => $this->id,
            'device_android_id' => $this->device_android_id,
            'slot_index' => $this->slot_index,
            'slot_label' => 'شريحة ' . ($this->slot_index + 1),
            'subscription_id' => $this->subscription_id,
            'carrier' => $this->carrier,
            'phone_number' => $this->phone_number,
            'total_balance' => (float) $this->total_balance,
            'total_actual_balance' => (float) $this->total_actual_balance,
            'financial_accounts' => FinancialAccountResource::collection($this->whenLoaded('financialAccounts', fn() => $this->financialAccounts, fn() => $this->financialAccounts()->with('messageSource')->get())),
            'status' => $this->status,
            'is_active' => $this->status === 'active',
            'provider' => $this->carrier,
            'note' => $this->note,
            'device_name' => $this->device?->device_name ?? 'غير محدد',
            'device_brand' => $this->device?->brand ?? null,
            'device_model' => $this->device?->model ?? null,
            'device' => [
                'id' => $this->device?->id,
                'name' => $this->device?->device_name ?? 'غير محدد',
                'brand' => $this->device?->brand,
                'model' => $this->device?->model,
                'identifier' => $this->device_android_id,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
