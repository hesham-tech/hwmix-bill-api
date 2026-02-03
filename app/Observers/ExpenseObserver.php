<?php

namespace App\Observers;

use App\Models\Expense;

class ExpenseObserver
{
    /**
     * Handle the Expense "created" event.
     */
    public function created(\App\Models\Expense $expense): void
    {
        app(\App\Services\FinancialLedgerService::class)->recordExpense($expense);
        $this->dispatchSummaryUpdate($expense);
    }

    /**
     * Handle the Expense "updated" event.
     */
    public function updated(Expense $expense): void
    {
        if ($expense->wasChanged('amount') || $expense->wasChanged('expense_date')) {
            $this->dispatchSummaryUpdate($expense);

            if ($expense->wasChanged('expense_date')) {
                // Update old date as well
                $this->dispatchSummaryUpdate($expense, $expense->getOriginal('expense_date'));
            }
        }
    }

    /**
     * Handle the Expense "deleted" event.
     */
    public function deleted(Expense $expense): void
    {
        $this->dispatchSummaryUpdate($expense);
    }

    /**
     * تحديث جداول الملخصات
     */
    protected function dispatchSummaryUpdate(Expense $expense, $date = null): void
    {
        $date = $date ?? $expense->expense_date;
        if ($date && $expense->company_id) {
            \App\Jobs\UpdateDailySalesSummary::dispatchSync(
                $date instanceof \Carbon\Carbon ? $date->toDateString() : (string) $date,
                $expense->company_id
            );
        }
    }

    /**
     * Handle the Expense "restored" event.
     */
    public function restored(Expense $expense): void
    {
        //
    }

    /**
     * Handle the Expense "force deleted" event.
     */
    public function forceDeleted(Expense $expense): void
    {
        //
    }
}
