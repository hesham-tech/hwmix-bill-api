<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\Auth;
use Exception;

class PayableService
{
    public function add(User $supplier, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $supplier->company_id ?? Auth::user()->active_company_id;

        $balanceRecord = StakeholderFinancialBalance::lockForUpdate()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $supplier->id,
                'relation_type' => 'payable',
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
            'user_id' => $supplier->id,
            'type' => 'payable_add',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'financial_operation_id' => $operationId,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'description' => $metadata['description'] ?? 'Add to payable'
        ]);
    }

    public function reduce(User $supplier, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $supplier->company_id ?? Auth::user()->active_company_id;

        $balanceRecord = StakeholderFinancialBalance::lockForUpdate()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $supplier->id,
                'relation_type' => 'payable',
            ],
            [
                'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            ]
        );

        $balanceBefore = (float)$balanceRecord->balance;
        $balanceAfter = $balanceBefore - $amount;

        if ($balanceAfter < 0 && !($metadata['allow_negative'] ?? false)) {
            throw new Exception("لا يمكن أن يكون الرصيد المستحق بالسالب، يرجى مراجعة الرصيد المستحق للمورد.");
        }

        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();

        Transaction::create([
            'company_id' => $companyId,
            'user_id' => $supplier->id,
            'type' => 'payable_reduce',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'financial_operation_id' => $operationId,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'description' => $metadata['description'] ?? 'Reduce payable'
        ]);
    }
}