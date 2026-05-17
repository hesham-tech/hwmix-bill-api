<?php

namespace Modules\Accounting\Observers;

use Modules\Accounting\Models\Expense;
use Modules\Accounting\Services\FinancialLedgerService;
use App\Jobs\UpdateDailySalesSummary;

/**
 * مراقب المصاريف (ExpenseObserver) - موديول المحاسبة
 */
class ExpenseObserver
{
    public function created(Expense $expense): void
    {
        app(FinancialLedgerService::class)->recordExpense($expense);
        $this->dispatchSummaryUpdate($expense);
    }

    public function updated(Expense $expense): void
    {
        if ($expense->wasChanged('amount') || $expense->wasChanged('expense_date')) {
            $this->dispatchSummaryUpdate($expense);

            if ($expense->wasChanged('expense_date')) {
                $this->dispatchSummaryUpdate($expense, $expense->getOriginal('expense_date'));
            }
        }
    }

    public function deleted(Expense $expense): void
    {
        $this->dispatchSummaryUpdate($expense);
    }

    protected function dispatchSummaryUpdate(Expense $expense, $date = null): void
    {
        $date = $date ?? $expense->expense_date;
        if ($date && $expense->company_id) {
            UpdateDailySalesSummary::dispatchSync(
                $date instanceof \Carbon\Carbon ? $date->toDateString() : (string) $date,
                $expense->company_id
            );
        }
    }
}
