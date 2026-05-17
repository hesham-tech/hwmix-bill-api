<?php

namespace Modules\Sales\Services\Traits;

use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceItem;
use App\Models\ProductVariant;
use Modules\Inventory\Models\Stock;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

trait InvoiceHelperTrait
{
    protected function createInvoice(array $data): Invoice
    {
        try {
            $invoice = Invoice::create([
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_type_id' => $data['invoice_type_id'],
                'invoice_type_code' => $data['invoice_type_code'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => $data['status'] ?? 'confirmed',
                'user_id' => $data['user_id'],
                'gross_amount' => $data['gross_amount'],
                'total_discount' => $data['total_discount'] ?? 0,
                'total_tax' => $data['total_tax'] ?? 0,
                'tax_rate' => $data['tax_rate'] ?? null,
                'tax_inclusive' => $data['tax_inclusive'] ?? false,
                'net_amount' => $data['net_amount'],
                'paid_amount' => $data['paid_amount'] ?? 0,
                'remaining_amount' => $data['remaining_amount'] ?? 0,
                'round_step' => $data['round_step'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'issue_date' => $data['issue_date'] ?? null,
                'previous_balance' => $data['previous_balance'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        try {
            $invoice->update([
                'invoice_type_id' => $data['invoice_type_id'],
                'invoice_type_code' => $data['invoice_type_code'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => $data['status'] ?? 'confirmed',
                'user_id' => $data['user_id'],
                'gross_amount' => $data['gross_amount'],
                'total_discount' => $data['total_discount'] ?? 0,
                'total_tax' => $data['total_tax'] ?? 0,
                'tax_rate' => $data['tax_rate'] ?? null,
                'tax_inclusive' => $data['tax_inclusive'] ?? false,
                'net_amount' => $data['net_amount'],
                'paid_amount' => $data['paid_amount'] ?? 0,
                'remaining_amount' => $data['remaining_amount'] ?? 0,
                'round_step' => $data['round_step'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'issue_date' => $data['issue_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'updated_by' => $data['updated_by'] ?? null,
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function createInvoiceItems(Invoice $invoice, array $items, $companyId = null, $createdBy = null): void
    {
        foreach ($items as $item) {
            try {
                $costPrice = $this->resolveItemCostPrice($invoice->invoice_type_code, $item['variant_id'] ?? null, $item['unit_price']);

                $profitMargin = $item['profit_margin'] ?? null;
                if (is_null($profitMargin) && !empty($item['variant_id'])) {
                    $variant = ProductVariant::find($item['variant_id']);
                    $profitMargin = $variant ? $variant->profit_margin : 0;
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $costPrice,
                    'total_cost' => $costPrice * $item['quantity'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'subtotal' => $item['subtotal'] ?? 0,
                    'total' => $item['total'],
                    'profit_margin' => $profitMargin ?? 0,
                    'service_id' => $item['service_id'] ?? null,
                    'subscription_id' => $item['subscription_id'] ?? null,
                    'company_id' => $companyId,
                    'created_by' => $createdBy,
                ]);
            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }

    protected function syncInvoiceItems(Invoice $invoice, array $newItemsData, $companyId = null, $updatedBy = null): void
    {
        try {
            $currentItems = $invoice->items->keyBy('id');
            $newItemsCollection = collect($newItemsData);

            $itemsToDelete = $currentItems->diffKeys($newItemsCollection->keyBy('id'));
            foreach ($itemsToDelete as $item) {
                $item->delete();
            }

            foreach ($newItemsCollection as $itemData) {
                if (isset($itemData['id']) && $existingItem = $currentItems->get($itemData['id'])) {
                    $costPrice = $existingItem->cost_price;
                    if (!$costPrice || $costPrice <= 0) {
                        $costPrice = $this->resolveItemCostPrice($invoice->invoice_type_code, $itemData['variant_id'] ?? null, $itemData['unit_price']);
                    }

                    $profitMargin = $itemData['profit_margin'] ?? null;
                    if (is_null($profitMargin) && !empty($itemData['variant_id'])) {
                        $variant = ProductVariant::find($itemData['variant_id']);
                        $profitMargin = $variant ? $variant->profit_margin : 0;
                    }

                    $existingItem->update([
                        'product_id' => $itemData['product_id'] ?? null,
                        'variant_id' => $itemData['variant_id'] ?? null,
                        'name' => $itemData['name'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'cost_price' => $costPrice,
                        'total_cost' => $costPrice * $itemData['quantity'],
                        'discount' => $itemData['discount'] ?? 0,
                        'tax_rate' => $itemData['tax_rate'] ?? 0,
                        'tax_amount' => $itemData['tax_amount'] ?? 0,
                        'subtotal' => $itemData['subtotal'] ?? 0,
                        'total' => $itemData['total'],
                        'profit_margin' => $profitMargin ?? 0,
                        'service_id' => $itemData['service_id'] ?? null,
                        'subscription_id' => $itemData['subscription_id'] ?? null,
                        'company_id' => $companyId,
                        'updated_by' => $updatedBy,
                    ]);
                } else {
                    $costPrice = $this->resolveItemCostPrice($invoice->invoice_type_code, $itemData['variant_id'] ?? null, $itemData['unit_price']);
                    $profitMargin = $itemData['profit_margin'] ?? null;
                    if (is_null($profitMargin) && !empty($itemData['variant_id'])) {
                        $variant = ProductVariant::find($itemData['variant_id']);
                        $profitMargin = $variant ? $variant->profit_margin : 0;
                    }

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $itemData['product_id'] ?? null,
                        'variant_id' => $itemData['variant_id'] ?? null,
                        'name' => $itemData['name'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'cost_price' => $costPrice,
                        'total_cost' => $costPrice * $itemData['quantity'],
                        'discount' => $itemData['discount'] ?? 0,
                        'tax_rate' => $itemData['tax_rate'] ?? 0,
                        'tax_amount' => $itemData['tax_amount'] ?? 0,
                        'subtotal' => $itemData['subtotal'] ?? 0,
                        'total' => $itemData['total'],
                        'profit_margin' => $profitMargin ?? 0,
                        'service_id' => $itemData['service_id'] ?? null,
                        'subscription_id' => $itemData['subscription_id'] ?? null,
                        'company_id' => $companyId,
                        'created_by' => $updatedBy,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function deleteInvoiceItems(Invoice $invoice): void
    {
        try {
            $invoice->items->each->delete();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function checkVariantsStock(array $items, string $mode = 'deduct', ?int $warehouseId = null): void
    {
        try {
            if ($mode === 'none') {
                return;
            }

            foreach ($items as $index => $item) {
                $itemWarehouseId = $item['warehouse_id'] ?? $warehouseId;
                $variantId = $item['variant_id'] ?? null;
                $variant = $variantId ? ProductVariant::find($variantId) : null;

                if (!$variant) {
                    throw ValidationException::withMessages([
                        "items.$index.variant_id" => ["المتغير المختار غير موجود (ID: $variantId)"],
                    ]);
                }

                $product = $variant->product;
                if ($product && !$product->requiresStock()) {
                    continue;
                }

                $totalAvailableQuantity = $variant->stocks()
                    ->where('status', 'available')
                    ->when($itemWarehouseId, function ($query) use ($itemWarehouseId) {
                        return $query->where('warehouse_id', $itemWarehouseId);
                    })
                    ->sum('quantity');

                if ($mode === 'deduct' && $totalAvailableQuantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => ['الكمية غير متوفرة في المخزون.'],
                    ]);
                }
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function deductStockForItems(array $items, ?int $warehouseId = null): void
    {
        try {
            foreach ($items as $item) {
                $itemWarehouseId = $item['warehouse_id'] ?? $warehouseId;
                $variant = ProductVariant::find($item['variant_id'] ?? null);
                if (!$variant) continue;
                if (!$variant->product?->requiresStock()) continue;

                $remaining = $item['quantity'];
                $stocks = $variant->stocks()
                    ->where('status', 'available')
                    ->when($itemWarehouseId, function ($query) use ($itemWarehouseId) {
                        return $query->where('warehouse_id', $itemWarehouseId);
                    })
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($stocks as $stock) {
                    if ($remaining <= 0) break;
                    $deduct = min($remaining, $stock->quantity);
                    if ($deduct > 0) {
                        $stock->quantity -= $deduct;
                        $stock->save();
                        $remaining -= $deduct;
                    }
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function returnStockForItems(Invoice $invoice): void
    {
        try {
            foreach ($invoice->items as $item) {
                $variant = ProductVariant::find($item->variant_id ?? null);
                if (!$variant) continue;
                if (!$variant->product?->requiresStock()) continue;

                $remaining = $item->quantity;
                $itemWarehouseId = $item->warehouse_id ?? $invoice->warehouse_id;

                $stock = $variant->stocks()
                    ->where('status', 'available')
                    ->when($itemWarehouseId, function ($query) use ($itemWarehouseId) {
                        return $query->where('warehouse_id', $itemWarehouseId);
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($stock) {
                    $stock->quantity += $remaining;
                    $stock->save();
                } else {
                    Stock::create([
                        'variant_id' => $variant->id,
                        'warehouse_id' => $itemWarehouseId,
                        'quantity' => $remaining,
                        'status' => 'available',
                        'company_id' => $invoice->company_id,
                        'created_by' => $invoice->updated_by ?? $invoice->created_by,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function incrementStockForItems(array $items, ?int $companyId = null, ?int $createdBy = null, ?int $warehouseId = null): void
    {
        try {
            foreach ($items as $item) {
                $itemWarehouseId = $item['warehouse_id'] ?? $warehouseId;
                $variant = ProductVariant::find($item['variant_id'] ?? null);
                if (!$variant) continue;
                if (!$variant->product?->requiresStock()) continue;

                $stock = $variant->stocks()
                    ->where('status', 'available')
                    ->when($itemWarehouseId, function ($query) use ($itemWarehouseId) {
                        return $query->where('warehouse_id', $itemWarehouseId);
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($stock) {
                    $stock->quantity += $item['quantity'];
                    $stock->save();
                } else {
                    Stock::create([
                        'variant_id' => $item['variant_id'],
                        'warehouse_id' => $itemWarehouseId,
                        'quantity' => $item['quantity'],
                        'status' => 'available',
                        'company_id' => $companyId,
                        'created_by' => $createdBy,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function decrementStockForInvoiceItems(Invoice $invoice): void
    {
        try {
            foreach ($invoice->items as $item) {
                $variant = ProductVariant::find($item->variant_id ?? null);
                if (!$variant) continue;
                if (!$variant->product?->requiresStock()) continue;

                $remainingToDeduct = $item->quantity;
                $itemWarehouseId = $item->warehouse_id ?? $invoice->warehouse_id;

                $stocks = $variant->stocks()
                    ->where('status', 'available')
                    ->when($itemWarehouseId, function ($query) use ($itemWarehouseId) {
                        return $query->where('warehouse_id', $itemWarehouseId);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($stocks as $stock) {
                    if ($remainingToDeduct <= 0) break;
                    $deduct = min($remainingToDeduct, $stock->quantity);
                    if ($deduct > 0) {
                        $stock->quantity -= $deduct;
                        $stock->save();
                        $remainingToDeduct -= $deduct;
                    }
                }

                if ($remainingToDeduct > 0) {
                    throw new \Exception("فشل خصم كامل الكمية للمتغير ID: {$variant->id}. الكمية المتبقية للخصم: {$remainingToDeduct}");
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function resolveItemCostPrice(?string $invoiceTypeCode, $variantId, float $unitPrice): float
    {
        if (in_array($invoiceTypeCode, ['purchase', 'return_purchase'])) {
            return (float) $unitPrice;
        }

        if ($variantId) {
            $stockCost = (float) \DB::table('stocks')
                ->where('variant_id', $variantId)
                ->where('cost', '>', 0)
                ->latest('created_at')
                ->value('cost');

            if ($stockCost > 0) {
                return $stockCost;
            }

            $variant = ProductVariant::find($variantId);
            $catalogPrice = (float) ($variant?->purchase_price ?? 0);

            if ($catalogPrice > 0) {
                return $catalogPrice;
            }
        }

        return 0;
    }
}
