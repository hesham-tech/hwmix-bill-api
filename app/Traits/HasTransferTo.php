<?php
// Temporary helper trait to add transferTo method
namespace App\Traits;

use App\Models\User;
use App\Models\CashBox;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

trait HasTransferTo
{
    public function transferTo(User $targetUser, float $amount, int $fromCashBoxId, int $toCashBoxId, $description = null): bool
    {
        $amount = floatval($amount);
        DB::beginTransaction();

        try {
            $fromCashBox = CashBox::findOrFail($fromCashBoxId);
            $toCashBox = CashBox::findOrFail($toCashBoxId);

            if ($fromCashBox->balance < $amount) {
                throw new \Exception('الرصيد غير كافٍ');
            }

            $balanceBeforeFrom = $fromCashBox->balance;
            $balanceBeforeTo = $toCashBox->balance;

            $fromCashBox->decrement('balance', $amount);
            $toCashBox->increment('balance', $amount);

            Transaction::create([
                'user_id' => $this->id,
                'cashbox_id' => $fromCashBox->id,
                'target_user_id' => $targetUser->id,
                'target_cashbox_id' => $toCashBox->id,
                'created_by' => Auth::id() ?? $this->id,
                'company_id' => $fromCashBox->company_id,
                'type' => 'تحويل صادر',
                'amount' => $amount,
                'balance_before' => $balanceBeforeFrom,
                'balance_after' => $fromCashBox->fresh()->balance,
                'description' => $description,
            ]);

            Transaction::create([
                'user_id' => $targetUser->id,
                'cashbox_id' => $toCashBox->id,
                'target_user_id' => $this->id,
                'target_cashbox_id' => $fromCashBox->id,
                'created_by' => Auth::id() ?? $this->id,
                'company_id' => $toCashBox->company_id,
                'type' => 'تحويل وارد',
                'amount' => $amount,
                'balance_before' => $balanceBeforeTo,
                'balance_after' => $toCashBox->fresh()->balance,
                'description' => $description,
            ]);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
