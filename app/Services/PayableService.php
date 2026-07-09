<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * خدمة إدارة ذمم الموردين التراكمية (Payables).
 */
class PayableService
{
    /**
     * إثبات وزيادة الالتزامات للمورد
     */
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

        $tx = Transaction::create([
            'company_id' => $companyId,
            'branch_id' => $metadata['branch_id'] ?? null,
            'user_id' => $supplier->id,
            'cash_box_id' => null,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'type' => 'deposit', // التزام جديد مستحق للمورد
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $metadata['description'] ?? "إثبات التزام للمورد بقيمة {$amount}",
            'financial_operation_id' => $operationId,
        ]);

        $balanceRecord->last_transaction_id = $tx->id;
        $balanceRecord->save();
    }

    /**
     * تخفيض الالتزامات للمورد (سداد للمورد)
     */
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
            throw new Exception("لا يمكن أن يصبح رصيد ذمة المورد سالباً (سداد زائد) دون معالجتها محاسبياً كأرصدة إيجابية.");
        }

        $balanceRecord->balance = $balanceAfter;
        $balanceRecord->save();

        $tx = Transaction::create([
            'company_id' => $companyId,
            'branch_id' => $metadata['branch_id'] ?? null,
            'user_id' => $supplier->id,
            'cash_box_id' => null,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'type' => 'withdraw', // سداد وتقليل الالتزام
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $metadata['description'] ?? "سداد التزام للمورد بقيمة {$amount}",
            'financial_operation_id' => $operationId,
        ]);

        $balanceRecord->last_transaction_id = $tx->id;
        $balanceRecord->save();
    }
}
