<?php

namespace App\Observers;

use App\Models\InvoicePayment;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class PaymentObserver
{
    /**
     * Handle the InvoicePayment "created" event.
     */
    public function created(InvoicePayment $payment): void
    {
        // 1. Log Activity
        ActivityLog::create([
            'action' => 'paid',
            'description' => "تم تسجيل دفعة مبلغ " . number_format((float) $payment->amount, 2) . " للفاتورة #" . ($payment->invoice->invoice_number ?? $payment->invoice_id),
            'subject_type' => \App\Models\Invoice::class,
            'subject_id' => $payment->invoice_id,
            'user_id' => Auth::id(),
            'company_id' => $payment->company_id,
        ]);

        // 2. Send Email Notification
        app(NotificationService::class)->notifyPaymentReceived($payment);

        // 3. Record Ledger Entry
        $this->recordLedgerEntry($payment);
    }

    /**
     * تسجيل العملية في دفتر الأستاذ
     */
    protected function recordLedgerEntry(InvoicePayment $payment): void
    {
        $ledgerService = app(\App\Services\FinancialLedgerService::class);
        $invoice = $payment->invoice;

        if (!$invoice)
            return;

        $typeCode = $invoice->invoiceType?->code;
        $description = "دفعة لمبلغ " . number_format((float) $payment->amount, 2) . " - فاتورة رقم: {$invoice->invoice_number}";

        if ($typeCode === 'sale') {
            // في المبيعات: زيادة في النقدية (Asset - Debit)
            $ledgerService->recordEntry($payment, 'asset', (float) $payment->amount, 'debit', "تحصيل نقدية: " . $description);
        } elseif ($typeCode === 'return_sale') {
            // في مرتجع المبيعات: دفع نقدية للعميل (Asset - Credit)
            $ledgerService->recordEntry($payment, 'asset', (float) $payment->amount, 'credit', "رد نقدية للعميل (مرتجع مبيعات): " . $description);
        } elseif ($typeCode === 'purchase') {
            // في المشتريات: نقص في النقدية (Asset - Credit)
            $ledgerService->recordEntry($payment, 'asset', (float) $payment->amount, 'credit', "سداد نقدية: " . $description);
        } elseif ($typeCode === 'return_purchase') {
            // في مرتجع المشتريات: تحصيل نقدية من المورد (Asset - Debit)
            $ledgerService->recordEntry($payment, 'asset', (float) $payment->amount, 'debit', "تحصيل نقدية من المورد (مرتجع مشتريات): " . $description);
        }
    }
}
