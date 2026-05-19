<?php

namespace Modules\Sales\Observers;

use Modules\Sales\Models\InvoicePayment;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use Modules\Accounting\Services\FinancialLedgerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Sales\Models\Invoice;

// مراقب عمليات دفع الفواتير لموديول المبيعات لتسجيل الأنشطة وتعديل الأرصدة المالية
class PaymentObserver
{
    public function created(InvoicePayment $payment): void
    {
        ActivityLog::create([
            'action' => 'paid',
            'description' => "تم تسجيل دفعة مبلغ " . number_format((float) $payment->amount, 2) . " للفاتورة #" . ($payment->invoice?->invoice_number ?? $payment->invoice_id),
            'model' => Invoice::class,
            'row_id' => $payment->invoice_id,
            'user_id' => Auth::id(),
            'company_id' => $payment->company_id,
        ]);

        app(NotificationService::class)->notifyPaymentReceived($payment);
        $this->recordLedgerEntry($payment);
        $this->clearDashboardCache($payment);
    }

    protected function recordLedgerEntry(InvoicePayment $payment): void
    {
        $ledgerService = app(FinancialLedgerService::class);
        $invoice = $payment->invoice;

        if (!$invoice) return;

        $typeCode = $invoice->invoiceType?->code;
        $description = "دفعة لمبلغ " . number_format((float) $payment->amount, 2) . " - فاتورة رقم: {$invoice->invoice_number}";

        if ($typeCode === 'sale') {
            $ledgerService->recordEntry($payment, 'asset', (float) $payment->amount, 'debit', "تحصيل نقدية: " . $description);
        } elseif ($typeCode === 'return_sale') {
            $ledgerService->recordEntry($payment, 'asset', (float) $payment->amount, 'credit', "رد نقدية للعميل (مرتجع مبيعات): " . $description);
        } elseif ($typeCode === 'purchase') {
            $ledgerService->recordEntry($payment, 'asset', (float) $payment->amount, 'credit', "سداد نقدية: " . $description);
        } elseif ($typeCode === 'return_purchase') {
            $ledgerService->recordEntry($payment, 'asset', (float) $payment->amount, 'debit', "تحصيل نقدية من المورد (مرتجع مشتريات): " . $description);
        }
    }

    protected function clearDashboardCache(InvoicePayment $payment): void
    {
        if ($payment->company_id) {
            Cache::increment("dashboard_version_{$payment->company_id}");
        }
    }
}
