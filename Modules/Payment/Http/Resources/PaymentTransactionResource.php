<?php

namespace Modules\Payment\Http\Resources;

//   مورد بيانات معاملة الدفع الإلكتروني لتحويل الكائن إلى استجابة JSON للعملاء أو الداشبورد.

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'payment_gateway_id' => $this->payment_gateway_id,
            'gateway_name' => $this->gateway?->name,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'gateway_reference' => $this->gateway_reference,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
