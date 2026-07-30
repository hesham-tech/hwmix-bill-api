<?php
// كلاس تحديث الرصيد الفعلي لخط المحفظة بناء على نواتج التحليل المعتمدة.

namespace Modules\HwnixCash\Services\Processing;

use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\Models\HwnixCashLine;

class WalletBalanceUpdater
{
    /**
     * تحديث الرصيد الفعلي (actual_balance) لخط المحفظة مباشرة من الرسالة كـ Source of Truth.
     */
    public function updateActualBalance(HwnixCashLine $line, float $newActualBalance): void
    {
        $line->update(['actual_balance' => $newActualBalance]);
        Log::info("[WalletBalanceUpdater] Updated actual_balance for Line ID {$line->id} ({$line->phone_number}) to {$newActualBalance} EGP");
    }
}
