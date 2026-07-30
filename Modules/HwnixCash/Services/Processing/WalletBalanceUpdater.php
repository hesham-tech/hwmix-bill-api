<?php
// كلاس تحديث الرصيد الفعلي لخط المحفظة بناء على نواتج التحليل المعتمدة.

namespace Modules\HwnixCash\Services\Processing;

use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\Models\HwnixCashFinancialAccount;

class WalletBalanceUpdater
{
    /**
     * تحديث الرصيد الفعلي (actual_balance) للحساب المالي مباشرة من الرسالة كـ Source of Truth.
     */
    public function updateActualBalance(HwnixCashFinancialAccount $account, float $newActualBalance): void
    {
        $account->update(['actual_balance' => $newActualBalance]);
        Log::info("[WalletBalanceUpdater] Updated actual_balance for FinancialAccount ID {$account->id} ({$account->name}) to {$newActualBalance} EGP");
    }
}
