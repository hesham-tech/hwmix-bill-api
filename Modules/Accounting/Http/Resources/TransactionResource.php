<?php

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserBasicResource;

class TransactionResource extends JsonResource
{
    private function getCashboxName()
    {
        return $this->customer && $this->cashbox_id
            ? optional($this->customer->cashBoxes->firstWhere('id', $this->cashbox_id))->name
            : 'خزنة غير معروفة';
    }

    private function getTargetCashboxName()
    {
        if (!$this->targetCustomer || !$this->target_cashbox_id) {
            return "لا يوجد مستخدم هدف أو معرف خزنة هدف";
        }

        $cashBoxes = $this->targetCustomer->cashBoxes;
        if ($cashBoxes->isEmpty()) {
            return 'لا توجد محافظ';
        }

        $cashbox = $cashBoxes->firstWhere('id', (int) $this->target_cashbox_id);
        return $cashbox?->name ?? 'محفظة غير معروفة';
    }

    private function generateHumanReadableDescription()
    {
        $user = $this->customer ? $this->customer->nickname : 'مستخدم غير معروف';
        $targetUser = $this->targetCustomer ? $this->targetCustomer->nickname : 'مستخدم غير معروف';
        $cashboxName = $this->getCashboxName();
        $targetCashboxName = $this->getTargetCashboxName();

        $operationTexts = [
            'تحويل' => "تم تحويل مبلغ {$this->amount} من {$cashboxName} الخاصة بـ {$user} إلى {$targetCashboxName} الخاصة بـ {$targetUser}",
            'transfer_out' => "تم تحويل مبلغ {$this->amount} من {$cashboxName} الخاصة بـ {$user} إلى {$targetCashboxName} الخاصة بـ {$targetUser}",
            'transfer_in' => "تم استلام تحويل بمبلغ {$this->amount} في {$cashboxName} الخاصة بـ {$user} من {$targetCashboxName} الخاصة بـ {$targetUser}",
            'إيداع' => "تم إيداع مبلغ {$this->amount} في {$cashboxName} الخاصة بـ {$user} من {$targetCashboxName} الخاصة بـ {$targetUser}",
            'deposit' => "تم إيداع مبلغ {$this->amount} في {$cashboxName} الخاصة بـ {$user} من {$targetCashboxName} الخاصة بـ {$targetUser}",
            'سحب' => "تم سحب مبلغ {$this->amount} من {$cashboxName} الخاصة بـ {$user} إلى {$targetCashboxName} الخاصة بـ {$targetUser}",
            'withdraw' => "تم سحب مبلغ {$this->amount} من {$cashboxName} الخاصة بـ {$user} إلى {$targetCashboxName} الخاصة بـ {$targetUser}",
            'reverse_transfer' => "عكس عملية تحويل بمبلغ {$this->amount}",
            'reverse_deposit' => "عكس عملية إيداع بمبلغ {$this->amount}",
            'reverse_withdraw' => "عكس عملية سحب بمبلغ {$this->amount}",
            'دفع' => "تم دفع مبلغ {$this->amount} من {$cashboxName} الخاصة بـ {$user} إلى {$targetCashboxName} الخاصة بـ {$targetUser}",
            'استلام' => "تم استلام مبلغ {$this->amount} من {$cashboxName} الخاصة بـ {$user} إلى {$targetCashboxName} الخاصة بـ {$targetUser}",
        ];

        return $operationTexts[$this->type] ?? "تمت عملية {$this->type} بمبلغ {$this->amount} بواسطة {$user}";
    }

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'target_user_id' => $this->target_user_id,
            'cashbox_id' => $this->cashbox_id,
            'target_cashbox_id' => $this->target_cashbox_id,
            'original_transaction_id' => $this->original_transaction_id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'transaction_date' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'customer' => $customerResource = new UserBasicResource($this->whenLoaded('customer')),
            'user' => $customerResource,
            'target_customer' => new UserBasicResource($this->whenLoaded('targetCustomer')),
            'cashbox_name' => $this->getCashboxName(),
            'target_cashbox_name' => $this->getTargetCashboxName(),
            'readable_description' => $this->generateHumanReadableDescription(),
        ];
    }
}
