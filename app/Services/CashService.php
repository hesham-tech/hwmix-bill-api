<?php

namespace App\Services;

use App\Models\Transaction;
use Modules\Accounting\Models\CashBox;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * خدمة إدارة المقبوضات والمدفوعات النقدية والتحويلات على مستوى الخزن وصناديق النقدية.
 */
class CashService
{
    /**
     * إيداع مبلغ في خزنة بقفل مالي
     */
    public function deposit(float $amount, int $cashBoxId, string $operationId, array $metadata = []): void
    {
        $cashBox = CashBox::withoutGlobalScopes()->lockForUpdate()->findOrFail($cashBoxId);

        $balanceBefore = (float)$cashBox->balance;
        $balanceAfter = $balanceBefore + $amount;

        // تطبيق invariant الحسابات التراكمية
        if (bccomp((string)$balanceAfter, (string)($balanceBefore + $amount), 2) !== 0) {
            throw new Exception("عدم تطابق في رصيد الخزنة المحسوب.");
        }

        $cashBox->balance = $balanceAfter;
        $cashBox->save();

        Transaction::create([
            'company_id' => $cashBox->company_id,
            'branch_id' => $cashBox->branch_id,
            'user_id' => $cashBox->user_id ?? $metadata['user_id'] ?? Auth::id() ?? null,
            'cashbox_id' => $cashBox->id,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'type' => 'deposit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $metadata['description'] ?? "إيداع نقدي بالخزينة بقيمة {$amount}",
            'financial_operation_id' => $operationId,
        ]);
    }

    /**
     * سحب مبلغ من خزنة بقفل مالي
     */
    public function withdraw(float $amount, int $cashBoxId, string $operationId, array $metadata = []): void
    {
        $cashBox = CashBox::withoutGlobalScopes()->lockForUpdate()->findOrFail($cashBoxId);

        $balanceBefore = (float)$cashBox->balance;

        // Invariant: لا يمكن سحب مبلغ أكبر من الرصيد المتوفر
        if ($balanceBefore < $amount) {
            throw new Exception("الرصيد غير كاف بالخزينة '{$cashBox->name}' لإتمام العملية المالية.");
        }

        $balanceAfter = $balanceBefore - $amount;

        $cashBox->balance = $balanceAfter;
        $cashBox->save();

        Transaction::create([
            'company_id' => $cashBox->company_id,
            'branch_id' => $cashBox->branch_id,
            'user_id' => $cashBox->user_id ?? $metadata['user_id'] ?? Auth::id() ?? null,
            'cashbox_id' => $cashBox->id,
            'created_by' => Auth::id() ?? $metadata['created_by'] ?? null,
            'type' => 'withdraw',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $metadata['description'] ?? "صرف نقدي من الخزينة بقيمة {$amount}",
            'financial_operation_id' => $operationId,
        ]);
    }

    /**
     * تحويل بين خزنتين بقفل مالي مزدوج
     */
    public function transfer(int $fromBoxId, int $toBoxId, float $amount, string $operationId, ?string $desc = null): void
    {
        // قفل الخزنة المصدر أولاً لمنع Deadlocks (بالترتيب التصاعدي للمعرف)
        $firstId = min($fromBoxId, $toBoxId);
        $secondId = max($fromBoxId, $toBoxId);

        $firstBox = CashBox::withoutGlobalScopes()->lockForUpdate()->findOrFail($firstId);
        $secondBox = CashBox::withoutGlobalScopes()->lockForUpdate()->findOrFail($secondId);

        $fromBox = $firstId === $fromBoxId ? $firstBox : $secondBox;
        $toBox = $firstId === $toBoxId ? $firstBox : $secondBox;

        $balanceBeforeFrom = (float)$fromBox->balance;
        if ($balanceBeforeFrom < $amount) {
            throw new Exception("الرصيد غير كاف بالخزينة المصدر لإجراء التحويل.");
        }

        $balanceBeforeTo = (float)$toBox->balance;

        // تنفيذ الخصم والزيادة
        $fromBox->balance = $balanceBeforeFrom - $amount;
        $fromBox->save();

        $toBox->balance = $balanceBeforeTo + $amount;
        $toBox->save();

        $descText = $desc ?? "تحويل مالي بقيمة {$amount}";

        // تسجيل الحركتين وربطهما بالعملية
        Transaction::create([
            'company_id' => $fromBox->company_id,
            'branch_id' => $fromBox->branch_id,
            'user_id' => $fromBox->user_id,
            'cashbox_id' => $fromBox->id,
            'created_by' => Auth::id(),
            'type' => 'transfer_out',
            'amount' => $amount,
            'balance_before' => $balanceBeforeFrom,
            'balance_after' => $fromBox->balance,
            'description' => "{$descText} (صرف للتحويل)",
            'financial_operation_id' => $operationId,
        ]);

        Transaction::create([
            'company_id' => $toBox->company_id,
            'branch_id' => $toBox->branch_id,
            'user_id' => $toBox->user_id,
            'cashbox_id' => $toBox->id,
            'created_by' => Auth::id(),
            'type' => 'transfer_in',
            'amount' => $amount,
            'balance_before' => $balanceBeforeTo,
            'balance_after' => $toBox->balance,
            'description' => "{$descText} (قبض للتحويل)",
            'financial_operation_id' => $operationId,
        ]);
    }
}
