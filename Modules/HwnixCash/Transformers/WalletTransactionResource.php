<?php
// محول استجابة بيانات معاملات المحافظ الإلكترونية للـ API.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_id' => $this->line_id,
            'operation_type' => $this->operation_type,
            'transaction_type' => $this->operation_type,
            'provider' => $this->provider,
            'status' => $this->status,
            'source' => $this->source,
            'amount' => (float) $this->amount,
            'fee' => (float) $this->fee,
            'balance_after' => $this->balance_after !== null ? (float) $this->balance_after : null,
            'currency' => $this->currency ?? 'EGP',
            'operation_number' => $this->operation_number,
            'reference_number' => $this->operation_number,
            'operation_at' => $this->operation_at?->toIso8601String(),
            'transaction_date' => $this->operation_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'target_phone' => $this->target_phone,
            'target_name' => $this->target_name,
            'bill_number' => $this->bill_number,
            'raw_sms' => $this->raw_sms,
            'metadata' => $this->metadata,
            'line' => $this->line ? [
                'id' => $this->line->id,
                'phone_number' => $this->line->phone_number,
                'carrier' => $this->line->carrier,
                'provider' => $this->line->carrier,
                'device_name' => $this->line->device?->device_name ?? 'غير محدد',
                'device_brand' => $this->line->device?->brand,
                'device_model' => $this->line->device?->model,
                'slot_index' => $this->line->slot_index,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
