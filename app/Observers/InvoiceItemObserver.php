<?php

namespace App\Observers;

use App\Models\InvoiceItem;

class InvoiceItemObserver
{
    /**
     * Handle the InvoiceItem "created" event.
     */
    public function created(InvoiceItem $invoiceItem): void
    {
        // Increase sales_count only for sales and services contexts
        $context = $invoiceItem->invoice?->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            // Increase sales_count for the variant
            if ($invoiceItem->variant_id) {
                $invoiceItem->variant()->increment('sales_count');
            }

            // Increase sales_count for the product
            if ($invoiceItem->product_id) {
                $invoiceItem->product()->increment('sales_count');
            }

            // 🔄 إنشاء اشتراك تلقائي إذا كان البند خدمة
            if ($invoiceItem->service_id && $invoiceItem->invoice?->customer_id) {
                \App\Models\Subscription::create([
                    'user_id' => $invoiceItem->invoice->customer_id,
                    'service_id' => $invoiceItem->service_id,
                    'company_id' => $invoiceItem->company_id,
                    'created_by' => $invoiceItem->created_by,
                    'starts_at' => now(),
                    'next_billing_date' => now()->addMonth(), // الافتراضي شهر واحد
                    'billing_cycle' => 'monthly',
                    'price' => $invoiceItem->unit_price,
                    'status' => 'active',
                    'auto_renew' => true,
                    'notes' => 'تم إنشاؤه تلقائياً من فاتورة رقم #' . $invoiceItem->invoice->invoice_number,
                ]);
            }
        }
    }

    /**
     * Handle the InvoiceItem "deleted" event.
     */
    public function deleted(InvoiceItem $invoiceItem): void
    {
        $context = $invoiceItem->invoice?->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            if ($invoiceItem->variant_id) {
                $invoiceItem->variant()->decrement('sales_count');
            }

            if ($invoiceItem->product_id) {
                $invoiceItem->product()->decrement('sales_count');
            }
        }
    }
}
