<?php
namespace App\Http\Resources\Subscription;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'plan_id' => $this->plan_id,
            'unique_identifier' => $this->unique_identifier,
            'start_date' => $this->start_date,
            'starts_at' => $this->starts_at,
            'next_billing_date' => $this->next_billing_date,
            'ends_at' => $this->ends_at,
            'billing_cycle' => $this->billing_cycle,
            'price' => $this->price,
            'partial_payment' => $this->partial_payment,
            'status' => $this->status,
            'auto_renew' => $this->auto_renew,
            'renewal_type' => $this->renewal_type,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user'),
            'service' => $this->whenLoaded('service'),
            'plan' => $this->whenLoaded('plan'),
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
