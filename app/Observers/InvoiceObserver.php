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
        // Increase sales_count only for sales and services contexts
        $context = $invoice->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            if ($invoice->user_id && $invoice->company_id) {
                CompanyUser::where('user_id', $invoice->user_id)
                    ->where('company_id', $invoice->company_id)
                    ->increment('sales_count');
            }
        }

        // تسجيل في دفتر الأستاذ إذا كانت الفاتورة مؤكدة منذ البداية
        if (in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])) {
            $this->recordLedgerEntry($invoice);
            $this->dispatchSummaryUpdate($invoice);
        }
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        // إذا تغيرت الحالة لتصبح مؤكدة/مدفوعة ولم تكن كذلك من قبل
        if (
            $invoice->wasChanged('status') &&
            in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])
        ) {
            $this->recordLedgerEntry($invoice);
            $this->dispatchSummaryUpdate($invoice);
        } elseif ($invoice->wasChanged('net_amount') && in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])) {
            $this->dispatchSummaryUpdate($invoice);
        }
    }

    /**
     * تسجيل القيد المحاسبي بناءً على نوع الفاتورة
     */
    protected function recordLedgerEntry(Invoice $invoice): void
    {
        $ledgerService = app(\App\Services\FinancialLedgerService::class);
        $typeCode = $invoice->invoiceType?->code;

        if ($typeCode === 'sale') {
            $ledgerService->recordSaleInvoice($invoice);
            $ledgerService->recordCogs($invoice);
        } elseif ($typeCode === 'return_sale') {
            $ledgerService->recordSaleReturnInvoice($invoice);
        } elseif ($typeCode === 'purchase') {
            $ledgerService->recordPurchaseInvoice($invoice);
        } elseif ($typeCode === 'return_purchase') {
            $ledgerService->recordPurchaseReturnInvoice($invoice);
        } elseif ($typeCode === 'service') {
            $ledgerService->recordSaleInvoice($invoice);
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

        if (in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])) {
            $this->dispatchSummaryUpdate($invoice);
        }
    }

    /**
     * تحديث جداول الملخصات
     */
    protected function dispatchSummaryUpdate(Invoice $invoice): void
    {
        $date = $invoice->issue_date ?? $invoice->created_at;
        if ($date && $invoice->company_id) {
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($date, $invoice) {
                \App\Jobs\UpdateDailySalesSummary::dispatchSync(
                    $date->toDateString(),
                    $invoice->company_id
                );
            });
        }
    }
}
