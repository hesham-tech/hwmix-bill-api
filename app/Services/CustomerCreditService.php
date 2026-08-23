<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * خدمة إدارة الرصيد الدائن المسبق للعملاء (Customer Credit / Overpayments).
 */
class CustomerCreditService
{
    /**
     * إيداع رصيد دائن مسبق للعميل (مثال: دفعات زائدة)
     */
    public function addCredit(User $customer, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $customer->company_id ?? Auth::user()->active_company_id;

        $balanceRecord = StakeholderFinancialBalance::lockForUpdate()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $customer->id,
                'relation_type' => 'credit',
            ],
            [
                'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            ]
        );

        $balanceBefore = (float)$balanceRecord->balance;
        $balanceAfter = $balanceBefore + $amount;

        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();
    }

    /**
     * استهلاك رصيد دائن مسبق للعميل لسداد مديونية أو فاتورة
     */
    public function consumeCredit(User $customer, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $customer->company_id ?? Auth::user()->active_company_id;

        $balanceRecord = StakeholderFinancialBalance::lockForUpdate()->where([
            'company_id' => $companyId,
            'user_id' => $customer->id,
            'relation_type' => 'credit',
        ])->first();

        $balanceBefore = $balanceRecord ? (float)$balanceRecord->balance : 0.00;

        if ($balanceBefore < $amount) {
            throw new Exception("رصيد العميل الدائن المسبق غير كاف لإتمام هذا الاستهلاك محاسبياً.");
        }

        $balanceAfter = $balanceBefore - $amount;
        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();
    }
}
