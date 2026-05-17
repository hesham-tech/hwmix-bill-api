<?php

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserBasicResource;

class RevenueResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'user_id' => $this->user_id,
            'created_by' => $this->created_by,
            'wallet_id' => $this->wallet_id,
            'company_id' => $this->company_id,
            'amount' => (float) $this->amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'payment_method' => $this->payment_method,
            'note' => $this->note,
            'revenue_date' => $this->revenue_date,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'customer' => new UserBasicResource($this->whenLoaded('customer')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
        ];
    }
}
