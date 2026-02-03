<?php

namespace App\Http\Resources\FinancialLedger;

use Illuminate\Http\Resources\Json\JsonResource;

class FinancialLedgerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'entry_date' => $this->entry_date->format('Y-m-d H:i:s'),
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'account_type' => $this->account_type,
            'type_label' => $this->getTypeLabel($this->source_type),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    protected function getTypeLabel($type)
    {
        $shortType = strtolower(class_basename($type));
        $labels = [
            'invoice' => 'فاتورة',
            'expense' => 'مصروف',
            'payment' => 'دفعة مسددة',
            'transaction' => 'تحويل مالي',
            'cashbox' => 'خزينة',
        ];
        return $labels[$shortType] ?? $shortType;
    }
}
