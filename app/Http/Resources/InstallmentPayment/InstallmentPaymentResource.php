<?php

namespace App\Http\Resources\InstallmentPayment;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPaymentResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'installment_plan_id' => $this->installment_plan_id,
            'payment_date' => $this->payment_date,
            'date' => $this->payment_date, // Alias for frontend
            'amount_paid' => (float) $this->amount_paid,
            'amount' => (float) $this->amount_paid, // Alias for frontend
            'excess_amount' => (float) ($this->excess_amount ?? 0),
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            'plan' => new \App\Http\Resources\InstallmentPlan\InstallmentPlanBasicResource($this->whenLoaded('plan')),
            'customer' => new \App\Http\Resources\User\UserBasicResource(
                $this->relationLoaded('plan')
                ? ($this->plan->customer ?? optional($this->plan->invoice)->customer)
                : null
            ),
            'creator' => new \App\Http\Resources\User\UserBasicResource($this->whenLoaded('creator')),
            'cash_box' => $this->whenLoaded('cashBox'),
        ];
    }
}
