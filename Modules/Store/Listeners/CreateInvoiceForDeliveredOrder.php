<?php

namespace Modules\Store\Listeners;

use Modules\Store\Events\SubOrderStatusChanged;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceItem;
use Modules\Sales\Models\InvoiceType;
use Modules\Sales\Models\InvoicePayment;
use Modules\Accounting\Models\CashBox;
use Modules\Inventory\Models\Stock;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateInvoiceForDeliveredOrder
{
    public function handle(SubOrderStatusChanged $event): void
    {
        $subOrder = $event->subOrder;

        // Ensure we only create invoice when status is delivered and no invoice exists yet
        if ($subOrder->status !== 'delivered' || $subOrder->invoice_id !== null) {
            return;
        }

        DB::transaction(function () use ($subOrder) {
            $companyId = $subOrder->company_id;
            
            // 1. Get the customer (user_id) from the parent order
            $parentOrder = $subOrder->order;
            if (!$parentOrder) return;
            
            $customerId = $parentOrder->customer_user_id;
            
            // 2. Get the sales invoice type
            $invoiceType = InvoiceType::withoutGlobalScopes()
                ->where('code', 'sales_invoice')
                ->orWhere('code', 'sales')
                ->first();
                
            if (!$invoiceType) {
                Log::error('CreateInvoiceForDeliveredOrder: Cannot find Sales InvoiceType.');
                return;
            }
            
            // 3. Create Invoice (Unpaid - as debt owed by the platform/customer to the vendor)
            $invoice = Invoice::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'user_id' => $customerId,
                'invoice_type_id' => $invoiceType->id,
                'status' => Invoice::STATUS_CONFIRMED,
                'payment_status' => Invoice::PAYMENT_UNPAID,
                'issue_date' => now(),
                'net_amount' => $subOrder->subtotal,
                'paid_amount' => 0,
                'remaining_amount' => $subOrder->subtotal,
                'total_amount' => $subOrder->subtotal,
                'total_tax' => 0,
                'discount_amount' => 0,
                'created_by' => $customerId, // or system
            ]);
            
            // 4. Create Invoice Items
            foreach ($subOrder->items as $item) {
                InvoiceItem::withoutGlobalScopes()->create([
                    'invoice_id' => $invoice->id,
                    'variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total_price,
                    'net_price' => $item->total_price,
                ]);
                
                // Finalize Stock Deduction (move reserved to actual deduction)
                $stocks = Stock::withoutGlobalScopes()
                    ->where('variant_id', $item->product_variant_id)
                    ->where('reserved', '>', 0)
                    ->lockForUpdate()
                    ->get();
                    
                $remainingToDeduct = $item->quantity;
                foreach ($stocks as $stock) {
                    if ($remainingToDeduct <= 0) break;
                    
                    $toDeduct = min($stock->reserved, $remainingToDeduct);
                    $stock->decrement('reserved', $toDeduct);
                    $stock->decrement('quantity', $toDeduct); // Actual deduction
                    $remainingToDeduct -= $toDeduct;
                }
            }
            
            // Note: We DO NOT insert payments into the Cashbox here. 
            // This is a marketplace, so the Platform collects the money.
            // The invoice remains UNPAID (debt) until the Platform performs a "Settlement" (تسوية) with the company.
            
            // 5. Link invoice to sub-order
            $subOrder->update(['invoice_id' => $invoice->id]);
            
            Log::info("Invoice {$invoice->id} generated for StoreSubOrder {$subOrder->id}");
        });
    }
}
