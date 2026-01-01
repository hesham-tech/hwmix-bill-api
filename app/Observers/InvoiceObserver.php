<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Activity;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        // 1. Audit Log
        Activity::log([
            'action' => Activity::ACTION_CREATED,
            'description' => "تم إنشاء فاتورة {$invoice->invoiceType?->name} رقم #{$invoice->invoice_number}",
            'subject' => $invoice,
            'new_values' => [
                'invoice_number' => $invoice->invoice_number,
                'invoice_type' => $invoice->invoiceType?->name,
                'customer' => $invoice->user?->name,
                'net_amount' => $invoice->net_amount,
                'status' => $invoice->status,
            ],
        ]);

        // 2. Email Notification
        if (in_array($invoice->invoiceType?->code, ['sale', 'installment_sale'])) {
            app(\App\Services\NotificationService::class)->notifyInvoiceCreated($invoice);
        }
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        // Only log if there are actual changes
        if (empty($invoice->getChanges())) {
            return;
        }

        $changes = $invoice->getChanges();
        $description = "تم تعديل فاتورة #{$invoice->invoice_number}";

        // Add specific descriptions for important changes
        if (isset($changes['status'])) {
            $oldStatus = $invoice->getOriginal('status');
            $newStatus = $changes['status'];
            $description .= " - تغيير الحالة من {$oldStatus} إلى {$newStatus}";
        }

        if (isset($changes['paid_amount'])) {
            $description .= " - تحديث المبلغ المدفوع";
        }

        Activity::log([
            'action' => Activity::ACTION_UPDATED,
            'description' => $description,
            'subject' => $invoice,
            'old_values' => $invoice->getOriginal(),
            'new_values' => $changes,
        ]);
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        Activity::log([
            'action' => Activity::ACTION_DELETED,
            'description' => "تم حذف فاتورة #{$invoice->invoice_number}",
            'subject' => $invoice,
            'old_values' => $invoice->toArray(),
        ]);
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        Activity::log([
            'action' => Activity::ACTION_RESTORED,
            'description' => "تم استرجاع فاتورة #{$invoice->invoice_number}",
            'subject' => $invoice,
        ]);
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        Activity::log([
            'action' => 'force_deleted',
            'description' => "تم حذف فاتورة #{$invoice->invoice_number} نهائياً",
            'subject' => $invoice,
            'old_values' => $invoice->toArray(),
        ]);
    }
}
