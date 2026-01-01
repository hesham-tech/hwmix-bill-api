<?php

namespace App\Observers;

use App\Models\InvoicePayment;
use App\Models\Activity;
use App\Services\NotificationService;

class PaymentObserver
{
    /**
     * Handle the InvoicePayment "created" event.
     */
    public function created(InvoicePayment $payment): void
    {
        // 1. Log Activity
        Activity::log([
            'action' => Activity::ACTION_PAID,
            'description' => "تم تسجيل دفعة مبلغ " . number_format((float) $payment->amount, 2) . " للفاتورة #" . ($payment->invoice->invoice_number ?? $payment->invoice_id),
            'subject' => $payment->invoice,
            'metadata' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method_id,
            ]
        ]);

        // 2. Send Email Notification
        app(NotificationService::class)->notifyPaymentReceived($payment);
    }
}
