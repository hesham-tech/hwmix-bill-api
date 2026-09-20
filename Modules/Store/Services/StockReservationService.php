<?php

namespace Modules\Store\Services;

// خدمة حجز المخزون مؤقتاً عند إنشاء الطلبات وتحرير الحجز عند التأكيد أو الإلغاء
use Modules\Inventory\Models\Stock;
use Modules\Store\Models\StoreSubOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockReservationService
{
    /**
     * حجز المخزون لجميع عناصر Sub-Order
     */
    public function reserveForSubOrder(StoreSubOrder $subOrder, iterable $items): void
    {
        foreach ($items as $item) {
            $stock = Stock::withoutGlobalScopes()
                ->where('variant_id', $item['variant_id'])
                ->whereRaw('(quantity - reserved) >= ?', [$item['quantity']])
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $stock->increment('reserved', $item['quantity']);
            } else {
                Log::warning('StockReservation: لم يجد مخزون كافٍ للحجز', [
                    'variant_id' => $item['variant_id'],
                    'quantity'   => $item['quantity'],
                ]);
            }
        }
    }

    /**
     * تحرير حجز المخزون (عند الإلغاء)
     */
    public function releaseReservation(StoreSubOrder $subOrder): void
    {
        foreach ($subOrder->items as $item) {
            Stock::withoutGlobalScopes()
                ->where('variant_id', $item->product_variant_id)
                ->where('reserved', '>=', $item->quantity)
                ->update(['reserved' => DB::raw('reserved - ' . $item->quantity)]);
        }
    }

    /**
     * التحقق من توفر المخزون لكل العناصر قبل الطلب
     */
    public function validateAll(array $items): void
    {
        foreach ($items as $item) {
            $available = Stock::withoutGlobalScopes()
                ->where('variant_id', $item['variant_id'])
                ->selectRaw('COALESCE(SUM(quantity - reserved), 0) as available')
                ->value('available');

            if ($available < $item['quantity']) {
                $variant = \Modules\Inventory\Models\ProductVariant::withoutGlobalScopes()->find($item['variant_id']);
                throw new \Exception("المخزون غير كافٍ للمنتج: " . ($variant?->product?->name ?? $item['variant_id']));
            }
        }
    }
}
