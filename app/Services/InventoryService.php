<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Stock;
use App\Models\ProductVariant;
use App\Services\DocumentServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService implements DocumentServiceInterface
{
    /**
     * تنفيذ تعديلات المخزون.
     */
    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);

                if (!$variant) {
                    throw ValidationException::withMessages([
                        'items' => ["المتغير غير موجود للنوع المختار."],
                    ]);
                }

                // ✅ تخطي التعديلات للمنتجات التي لا تتطلب مخزون
                if (!$variant->product?->requiresStock()) {
                    continue;
                }

                if (isset($item['stock_id'])) {
                    // تعديل كمية في سجل مخزون محدد
                    $stock = Stock::findOrFail($item['stock_id']);
                    $stock->update([
                        'quantity' => $item['quantity'],
                    ]);
                } else {
                    // إضافة رصيد جديد لمستودع محدد (إنشاء سجل مخزون)
                    Stock::create([
                        'variant_id' => $variant->id,
                        'warehouse_id' => $item['warehouse_id'] ?? $data['warehouse_id'],
                        'quantity' => $item['quantity'],
                        'status' => 'available',
                        'company_id' => $data['company_id'],
                    ]);
                }
            }

            ActivityLog::create([
                'action' => 'تعديل المخزون',
                'user_id' => $data['created_by'],
                'details' => 'تم إجراء تعديلات مخزنية لعدد ' . count($data['items']) . ' أصناف.',
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'تم تعديل المخزون بنجاح',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(array $data, \App\Models\Invoice $invoice): \App\Models\Invoice
    {
        return $invoice;
    }

    public function cancel(\App\Models\Invoice $invoice): \App\Models\Invoice
    {
        return $invoice;
    }
}
