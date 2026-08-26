<?php

namespace App\Services;

use App\Models\Expense;
use App\Contracts\FinancialEngineInterface;
use App\Services\FinancialLedgerService;
use App\Services\FinancialOperationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpenseService
{
    protected FinancialEngineInterface $engine;
    protected FinancialLedgerService $ledgerService;
    protected FinancialOperationService $operationService;

    public function __construct(
        FinancialEngineInterface $engine,
        FinancialLedgerService $ledgerService,
        FinancialOperationService $operationService
    ) {
        $this->engine = $engine;
        $this->ledgerService = $ledgerService;
        $this->operationService = $operationService;
    }

    public function createExpense(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            $operationId = (string) Str::uuid();

            // 1. Create the Expense record
            $expense = Expense::create(array_merge($data, [
                'financial_operation_id' => $operationId,
                'status' => 'completed',
            ]));

            // 2. Create the Financial Operation envelope
            $this->operationService->createOperation([
                'id' => $operationId,
                'company_id' => $expense->company_id,
                'type' => 'expense',
                'amount' => $expense->amount,
                'source_type' => Expense::class,
                'source_id' => $expense->id,
                'metadata' => [
                    'description' => "تسجيل مصروف: {$expense->category?->name}",
                ],
            ]);

            // 3. Deduct from CashBox (This automatically creates the Transaction out)
            $this->engine->payMoney(
                (float) $expense->amount,
                (int) $expense->cash_box_id,
                $operationId,
                [
                    'company_id' => $expense->company_id,
                    'user_id' => $userId,
                    'description' => "سداد مصروف: {$expense->notes}"
                ]
            );

            // 4. Write Double-Entry Ledger
            $cashBoxModel = \Modules\Accounting\Models\CashBox::withoutGlobalScopes()->find($expense->cash_box_id) ?? $expense;

            // Debit: Expense
            $this->ledgerService->recordEntry(
                $expense,
                'expense',
                (float) $expense->amount,
                'debit',
                "إثبات مصروف: {$expense->category?->name} - {$expense->notes}",
                $expense->expense_date ? \Carbon\Carbon::parse($expense->expense_date) : now(),
                $operationId
            );

            // Credit: Asset (CashBox)
            $this->ledgerService->recordEntry(
                $cashBoxModel,
                'asset',
                (float) $expense->amount,
                'credit',
                "دفع مصروف من الصندوق",
                $expense->expense_date ? \Carbon\Carbon::parse($expense->expense_date) : now(),
                $operationId
            );

            return $expense;
        });
    }

    public function reverseExpense(Expense $expense, int $userId): void
    {
        if ($expense->status === 'reversed') {
            throw new \Exception('المصروف معكوس مسبقاً.');
        }

        if (empty($expense->financial_operation_id)) {
            throw new \Exception('لا يمكن عكس مصروف قديم لا يحتوي على رقم عملية مالية.');
        }

        DB::transaction(function () use ($expense) {
            // Reverse the core operation
            $this->engine->reverseOperation(
                $expense->financial_operation_id,
                "عكس مصروف: {$expense->category?->name}"
            );

            // Update status
            $expense->update(['status' => 'reversed']);
        });
    }
}
