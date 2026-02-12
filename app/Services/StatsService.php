<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StatsProductSummary;
use App\Models\StatsUserSummary;
use App\Models\StatsUserProductMatrix;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\Support\Facades\Log;

class StatsService
{
    /**
     * Update all relevant stats when an invoice is fully processed (e.g., Paid).
     */
    public function aggregateInvoiceStats(Invoice $invoice)
    {
        try {
            DB::transaction(function () use ($invoice) {
                // 1. Update User Summary
                $this->updateUserStats($invoice);

                // 2. Update Product Summaries and Matrix
                $invoice->items()->each(function (InvoiceItem $item) use ($invoice) {
                    if ($item->product_id) {
                        $this->updateProductStats($item);
                        $this->updateUserProductMatrix($invoice->user_id, $item);
                    }
                });
            });
        } catch (Throwable $e) {
            Log::error("Stats aggregation failed for Invoice #{$invoice->id}: " . $e->getMessage());
        }
    }

    /**
     * Update cumulative stats for a product.
     */
    protected function updateProductStats(InvoiceItem $item)
    {
        $stats = StatsProductSummary::firstOrCreate(
            ['product_id' => $item->product_id],
            ['company_id' => $item->company_id]
        );

        $stats->increment('total_sold_quantity', $item->quantity);
        $stats->increment('total_revenue', $item->total);

        // Profit calculation: (Price - Cost) * Quantity
        $profit = ($item->unit_price - $item->cost_price) * $item->quantity;
        $stats->increment('total_profit', $profit);

        $stats->increment('total_orders_count');
        $stats->update(['last_sold_at' => now()]);
    }

    /**
     * Update cumulative stats for a user (customer).
     */
    protected function updateUserStats(Invoice $invoice)
    {
        $stats = StatsUserSummary::firstOrCreate(
            ['user_id' => $invoice->user_id],
            ['company_id' => $invoice->company_id]
        );

        $stats->increment('total_spent', $invoice->total_amount);
        $stats->increment('orders_count');
        $stats->update(['last_order_at' => now()]);

        // RFM logic could be calculated here or in a separate scheduled task
    }

    /**
     * Update the relationship record between a specific user and product.
     */
    protected function updateUserProductMatrix($userId, InvoiceItem $item)
    {
        $stats = StatsUserProductMatrix::firstOrCreate(
            [
                'user_id' => $userId,
                'product_id' => $item->product_id,
            ],
            ['company_id' => $item->company_id]
        );

        $stats->increment('total_quantity', $item->quantity);
        $stats->increment('total_spent', $item->total);
        $stats->increment('purchase_count');
        $stats->update(['last_purchased_at' => now()]);
    }
}
