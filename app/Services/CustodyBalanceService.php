<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * خدمة إدارة عهد الموظفين المالية (Custodies).
 */
class CustodyBalanceService
{
    /**
     * إثبات زيادة عهدة الموظف
     */
    public function add(User $employee, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $employee->company_id ?? Auth::user()->active_company_id;

        $balanceRecord = StakeholderFinancialBalance::lockForUpdate()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $employee->id,
                'relation_type' => 'custody',
            ],
            [
                'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            ]
        );

        $balanceBefore = (float)$balanceRecord->balance;
        $balanceAfter = $balanceBefore + $amount;

        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();

        Transaction::create([
            'company_id' => $companyId,
            'user_id' => $employee->id,
            'type' => 'custody_add',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'financial_operation_id' => $operationId,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'description' => $metadata['description'] ?? 'إضافة عهدة'
        ]);
    }

    /**
     * تخفيض عهدة الموظف
     */
    public function reduce(User $employee, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $employee->company_id ?? Auth::user()->active_company_id;

        $balanceRecord = StakeholderFinancialBalance::lockForUpdate()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $employee->id,
                'relation_type' => 'custody',
            ],
            [
                'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            ]
        );

        $balanceBefore = (float)$balanceRecord->balance;
        $balanceAfter = $balanceBefore - $amount;

        if ($balanceAfter < 0 && !($metadata['allow_negative'] ?? false)) {
            throw new Exception("لا يمكن أن تصبح عهدة الموظف سالبة (تسوية أكبر من العهدة المسجلة).");
        }

        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();

        Transaction::create([
            'company_id' => $companyId,
            'user_id' => $employee->id,
            'type' => 'custody_reduce',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'financial_operation_id' => $operationId,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'description' => $metadata['description'] ?? 'تخفيض عهدة'
        ]);
    }
}