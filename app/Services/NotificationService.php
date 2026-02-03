<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Mail\InvoiceCreatedMail;
use App\Mail\PaymentReceivedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * إرسال إشعار عند إنشاء فاتورة جديدة
     */
    public function notifyInvoiceCreated(Invoice $invoice)
    {
        if (!$invoice->user || !$invoice->user->email) {
            return;
        }

        try {
            Mail::to($invoice->user->email)->send(new InvoiceCreatedMail($invoice));
        } catch (\Exception $e) {
            Log::error('فشل إرسال بريد الفاتورة: ' . $e->getMessage());
        }
    }

    /**
     * إرسال إشعار عند استلام دفعة
     */
    public function notifyPaymentReceived(InvoicePayment $payment)
    {
        $invoice = $payment->invoice;
        if (!$invoice || !$invoice->user || !$invoice->user->email) {
            return;
        }

        try {
            Mail::to($invoice->user->email)->send(new PaymentReceivedMail($payment));
        } catch (\Exception $e) {
            Log::error('فشل إرسال بريد الدفعة: ' . $e->getMessage());
        }
    }
}
