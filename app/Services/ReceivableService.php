<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * خدمة إدارة ذمم العملاء التراكمية (Receivables).
 */
class ReceivableService
{
    /**
     * إثبات وزيادة مديونية العميل (مدين)
     */
    public function add(User $customer, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $customer->company_id ?? Auth::user()->active_company_id;

        // قفل مالي مخصص للذمة التراكمية
        $balanceRecord = StakeholderFinancialBalance::lockForUpdate()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $customer->id,
                'relation_type' => 'receivable',
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
     * تخفيض مديونية العميل (دائن)
     */
    public function reduce(User $customer, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $customer->company_id ?? Auth::user()->active_company_id;

        $balanceRecord = StakeholderFinancialBalance::lockForUpdate()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $customer->id,
                'relation_type' => 'receivable',
            ],
            [
                'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            ]
        );

        $balanceBefore = (float)$balanceRecord->balance;
        $balanceAfter = $balanceBefore - $amount;

        // Invariant: لا يمكن أن تصبح ذمة العميل سالبة إلا إذا ولدنا رصيد دائن
        if ($balanceAfter < 0 && !($metadata['allow_negative'] ?? false)) {
            throw new Exception("لا يمكن أن تصبح ذمة العميل سالبة (تجاوز السداد) دون ترحيل الرصيد الزائد لحسابه الدائن.");
        }

        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();
    }
}
