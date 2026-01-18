<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\CompanyUser;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        // Increase sales_count only for sales and services contexts (exclude purchases/finance/inventory)
        $context = $invoice->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            if ($invoice->user_id && $invoice->company_id) {
                CompanyUser::where('user_id', $invoice->user_id)
                    ->where('company_id', $invoice->company_id)
                    ->increment('sales_count');
            }
        }
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        $context = $invoice->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            if ($invoice->user_id && $invoice->company_id) {
                CompanyUser::where('user_id', $invoice->user_id)
                    ->where('company_id', $invoice->company_id)
                    ->decrement('sales_count');
            }
        }
    }
}
