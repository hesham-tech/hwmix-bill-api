<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\ProductVariant;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService implements DocumentServiceInterface
{
    public function create(array $data): Invoice|array
    {
        DB::beginTransaction();
        try {
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);
                if (!$variant) throw ValidationException::withMessages(['items' => ["المتغير غير موجود للنوع المختار."]]);
                if (!$variant->product?->requiresStock()) continue;

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

    public function update(array $data, Invoice $invoice): Invoice
    {
        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        return $invoice;
    }
}
