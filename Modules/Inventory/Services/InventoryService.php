<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\ProductVariant;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// خدمة إدارة عمليات المخزون (التسويات والتحويلات) وتحديث كميات المستودعات
class InventoryService implements DocumentServiceInterface
{
    public function create(array $data)
    {
        // 1. تحديد نوع المستند
        $invoiceType = InvoiceType::find($data['invoice_type_id']);
        $invoiceTypeCode = $data['invoice_type_code'] ?? $invoiceType?->code ?? 'inventory_adjustment';

        // 2. التحقق من صحة عملية التحويل المخزني
        if ($invoiceTypeCode === 'stock_transfer') {
            $sourceWarehouseId = $data['warehouse_id'] ?? null;
            $destinationWarehouseId = $data['to_warehouse_id'] ?? null;

            if (!$sourceWarehouseId) {
                throw ValidationException::withMessages(['warehouse_id' => ['المستودع المصدر مطلوب لعقد التحويل.']]);
            }
            if (!$destinationWarehouseId) {
                throw ValidationException::withMessages(['to_warehouse_id' => ['المستودع المستهدف مطلوب لعقد التحويل.']]);
            }
            if ($sourceWarehouseId == $destinationWarehouseId) {
                throw ValidationException::withMessages(['to_warehouse_id' => ['لا يمكن التحويل لنفس المستودع.']]);
            }

            // تحقق من كميات الأصناف في المستودع المصدر
            foreach ($data['items'] as $index => $item) {
                $variant = ProductVariant::find($item['variant_id']);
                if (!$variant) {
                    throw ValidationException::withMessages(["items.$index.variant_id" => ['الصنف المختار غير موجود.']]);
                }
                if ($variant->product?->requiresStock()) {
                    $totalAvailable = Stock::where('variant_id', $variant->id)
                        ->where('warehouse_id', $sourceWarehouseId)
                        ->where('status', 'available')
                        ->sum('quantity');

                    if ($totalAvailable < $item['quantity']) {
                        throw ValidationException::withMessages([
                            "items.$index.quantity" => ["الكمية غير متوفرة في المستودع المصدر (المتوفر: $totalAvailable)."]
                        ]);
                    }
                }
            }
        }

        DB::beginTransaction();
        try {
            // 3. إنشاء مستند الفاتورة الرئيسي
            $invoice = Invoice::create([
                'invoice_type_id' => $data['invoice_type_id'],
                'invoice_type_code' => $invoiceTypeCode,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
                'company_id' => $data['company_id'],
                'created_by' => $data['created_by'] ?? null,
                'user_id' => $data['user_id'] ?? $data['created_by'],
                'gross_amount' => 0,
                'net_amount' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'status' => 'confirmed',
                'notes' => $data['notes'] ?? null,
            ]);

            // 4. معالجة الأصناف وحركات المخزون
            foreach ($data['items'] as $index => $item) {
                $variant = ProductVariant::findOrFail($item['variant_id']);
                
                // إنشاء بند المستند
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'name' => $item['name'] ?? $variant->product?->name ?? 'منتج',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total' => $item['total'] ?? 0,
                    'company_id' => $data['company_id'],
                    'created_by' => $data['created_by'] ?? null,
                ]);

                if (!$variant->product?->requiresStock()) {
                    continue;
                }

                if ($invoiceTypeCode === 'stock_transfer') {
                    // --- تنفيذ عملية التحويل المخزني الفعلي (FIFO) ---
                    $remaining = $item['quantity'];
                    
                    // جلب كميات المخزن المصدر بالتسلسل الزمني
                    $sourceStocks = Stock::where('variant_id', $variant->id)
                        ->where('warehouse_id', $sourceWarehouseId)
                        ->where('status', 'available')
                        ->where('quantity', '>', 0)
                        ->orderBy('created_at', 'asc')
                        ->get();

                    foreach ($sourceStocks as $sourceStock) {
                        if ($remaining <= 0) break;

                        $deductQty = min($sourceStock->quantity, $remaining);
                        
                        // خصم الكمية من السجل الحالي
                        $sourceStock->decrement('quantity', $deductQty);
                        $remaining -= $deductQty;

                        // إضافة/إنشاء الكمية في المستودع المستهدف بنفس التكلفة والدفعة
                        Stock::create([
                            'variant_id' => $variant->id,
                            'warehouse_id' => $destinationWarehouseId,
                            'quantity' => $deductQty,
                            'cost' => $sourceStock->cost,
                            'batch' => $sourceStock->batch,
                            'expiry' => $sourceStock->expiry,
                            'loc' => $sourceStock->loc,
                            'status' => 'available',
                            'company_id' => $data['company_id'],
                            'created_by' => $data['created_by'] ?? null,
                            'branch_id' => \Modules\Inventory\Models\Warehouse::find($destinationWarehouseId)?->branch_id ?? $sourceStock->branch_id,
                        ]);
                    }
                } else {
                    // --- تنفيذ عملية تسوية المخزون (Adjustment) ---
                    if (isset($item['stock_id'])) {
                        $stock = Stock::findOrFail($item['stock_id']);
                        $stock->update(['quantity' => $item['quantity']]);
                    } else {
                        Stock::create([
                            'variant_id' => $variant->id,
                            'warehouse_id' => $item['warehouse_id'] ?? ($data['warehouse_id'] ?? null),
                            'quantity' => $item['quantity'],
                            'status' => 'available',
                            'company_id' => $data['company_id'],
                            'created_by' => $data['created_by'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();
            return $invoice;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(array $data, Invoice $invoice): Invoice
    {
        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        return $invoice;
    }
}
