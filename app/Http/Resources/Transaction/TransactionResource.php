<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Resources\Json\JsonResource;

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
            return "لا يوجد مستخدم هدف او معرف خزنه حدف";
        }

        // تخزين cashBoxes في متغير
        $cashBoxes = $this->targetCustomer->cashBoxes;

        // التأكد من أن cashBoxes ليست فارغة
        if ($cashBoxes->isEmpty()) {
            return 'لا توجد محافظ';
        }

        $cashbox = $cashBoxes->firstWhere('id', (int) $this->target_cashbox_id);

        return $cashbox->name;
    }


    // private function getTargetCashboxName()
    // {
    //     return $this->targetCustomer && $this->target_cashbox_id
    //         ? optional($this->targetCustomer->cashBoxes->firstWhere('id', (int) $this->target_cashbox_id))->name
    //         : 'محفظة غير معروفة';
    // }
    private function generateHumanReadableDescription()
    {
        $user = $this->customer ? $this->customer->nickname : 'مستخدم غير معروف';
        $targetUser = $this->targetCustomer ? $this->targetCustomer->nickname : 'مستخدم غير معروف';
        $cashboxName = $this->getCashboxName();
        $targetCashboxName = $this->getTargetCashboxName();

        $operationTexts = [
            'تحويل' => "تم تحويل مبلغ {$this->amount} من {$cashboxName} الخاصه ب {$user} إلى {$targetCashboxName} الخاصه ب {$targetUser}",
            'إيداع' => "تم إيداع مبلغ {$this->amount} في {$cashboxName} الخاصه ب {$user} من {$targetCashboxName} الخاصه ب {$targetUser}",
            'سحب' => "تم سحب مبلغ {$this->amount} من {$cashboxName} الخاصه ب {$user} إلى {$targetCashboxName} الخاصه ب {$targetUser}",
            'دفع' => "تم دفع مبلغ {$this->amount} من {$cashboxName} الخاصه ب {$user} إلى {$targetCashboxName} الخاصه ب {$targetUser}",
            'استلام' => "تم استلام مبلغ {$this->amount} من {$cashboxName} الخاصه ب {$user} إلى {$targetCashboxName} الخاصه ب {$targetUser}",
        ];

        // إرجاع النص حسب نوع العملية أو النص الافتراضي إذا لم يكن النوع موجود
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
            'amount' => $this->amount,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'customer' => new \App\Http\Resources\User\UserBasicResource($this->customer),
            'target_customer' => new \App\Http\Resources\User\UserBasicResource($this->targetCustomer),
            // 'user' => $this->user->cashBoxes,
            // 'targetUser' => $this->targetUser->cashBoxes,
            'cashbox_name' => $this->getCashboxName(),
            'target_cashbox_name' => $this->getTargetCashboxName(),
            'readable_description' => $this->generateHumanReadableDescription(),
        ];
    }


}
