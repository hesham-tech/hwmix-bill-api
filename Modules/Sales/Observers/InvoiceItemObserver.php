<?php

namespace Modules\Sales\Observers;

use Modules\Sales\Models\InvoiceItem;
use App\Models\Subscription;

class InvoiceItemObserver
{
    public function created(InvoiceItem $invoiceItem): void
    {
        $context = $invoiceItem->invoice?->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            if ($invoiceItem->variant_id) {
                $invoiceItem->variant()->increment('sales_count');
            }

            if ($invoiceItem->product_id) {
                $invoiceItem->product()->increment('sales_count');
            }

            if ($invoiceItem->service_id && $invoiceItem->invoice?->user_id) {
                Subscription::create([
                    'user_id' => $invoiceItem->invoice->user_id,
                    'service_id' => $invoiceItem->service_id,
                    'company_id' => $invoiceItem->company_id,
                    'created_by' => $invoiceItem->created_by,
                    'starts_at' => now(),
                    'next_billing_date' => now()->addMonth(),
                    'billing_cycle' => 'monthly',
                    'price' => $invoiceItem->unit_price,
                    'status' => 'active',
                    'auto_renew' => true,
                    'notes' => 'تم إنشاؤه تلقائياً من فاتورة رقم #' . $invoiceItem->invoice->invoice_number,
                ]);
            }
        }
    }

    public function deleted(InvoiceItem $invoiceItem): void
    {
        $context = $invoiceItem->invoice?->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            if ($invoiceItem->variant_id) {
                $invoiceItem->variant()->where('sales_count', '>', 0)->decrement('sales_count');
            }

            if ($invoiceItem->product_id) {
                $invoiceItem->product()->where('sales_count', '>', 0)->decrement('sales_count');
            }
        }
    }
}
