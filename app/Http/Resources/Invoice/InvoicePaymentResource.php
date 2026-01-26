<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PaymentMethod\PaymentMethodResource;
use App\Http\Resources\CashBox\CashBoxResource;

class InvoicePaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => number_format($this->amount, 2, '.', ''),
            'payment_date' => $this->payment_date ? $this->payment_date->format('Y-m-d') : null,
            'notes' => $this->notes,
            'reference_number' => $this->reference_number,
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'cash_box' => new CashBoxResource($this->whenLoaded('cashBox')),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
