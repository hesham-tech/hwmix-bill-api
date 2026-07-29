<?php

namespace App\Http\Resources\PartnerOperation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Transaction\TransactionResource;

/**
 * فئة تحويل سجل عملية الشريك إلى استجابة API موحدة
 */
class PartnerOperationResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'partner_id' => $this->partner_id,
            'partner' => new UserResource($this->whenLoaded('partner')),
            'cashbox_id' => $this->cashbox_id,
            'cash_box' => new CashBoxResource($this->whenLoaded('cashBox')),
            'transaction_id' => $this->transaction_id,
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
            'type' => $this->type,
            'type_label' => $this->getTypeLabelText($this->type),
            'category' => $this->getCategoryText($this->type),
            'amount' => (float) $this->amount,
            'operation_date' => $this->operation_date ? $this->operation_date->toIso8601String() : null,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * إرجاع النص العربي الوصفي لنوع العملية
     */
    private function getTypeLabelText(string $type): string
    {
        return match ($type) {
            'capital_increase' => 'زيادة رأس مال',
            'capital_withdrawal' => 'سحب من رأس المال',
            'partner_loan_given' => 'تقديم قرض للشركة',
            'partner_loan_repaid' => 'سداد قرض الشريك',
            'profit_distribution' => 'توزيع أرباح',
            'loss_coverage' => 'تغطية خسائر',
            default => $type,
        };
    }

    /**
     * إرجاع تصنيف الحركة (إيداع/سحب)
     */
    private function getCategoryText(string $type): string
    {
        return in_array($type, ['capital_increase', 'partner_loan_given', 'loss_coverage']) ? 'deposit' : 'withdraw';
    }
}
