<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\Auth;
use Exception;

class ReceivableService
{
    public function add(User $customer, float $amount, string $operationId, array $metadata = []): void
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
        $balanceAfter = $balanceBefore + $amount;
        \Log::info("Add: Before={$balanceBefore}, amount={$amount}, after={$balanceAfter}, companyId={$companyId}");

        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();

        Transaction::create([
            'company_id' => $companyId,
            'user_id' => $customer->id,
            'type' => 'receivable_add',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'financial_operation_id' => $operationId,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'description' => $metadata['description'] ?? 'Add to receivable'
        ]);
    }

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
        \Log::info("Reduce: Before={$balanceBefore}, amount={$amount}, after={$balanceAfter}, companyId={$companyId}");

        if ($balanceAfter < 0 && !($metadata['allow_negative'] ?? false)) {
            throw new Exception("???????? ?? ???? ??? ??? ?????? ????? (????? ??????) ??? ????? ?????? ?????? ?????? ??????.");
        }

        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();

        Transaction::create([
            'company_id' => $companyId,
            'user_id' => $customer->id,
            'type' => 'receivable_reduce',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'financial_operation_id' => $operationId,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'description' => $metadata['description'] ?? 'Reduce receivable'
        ]);
    }
}