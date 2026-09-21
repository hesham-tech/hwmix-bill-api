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
            $remainingToReserve = $item['quantity'];
            
            $stocks = Stock::withoutGlobalScopes()
                ->where('variant_id', $item['variant_id'])
                ->whereRaw('(quantity - reserved) > 0')
                ->lockForUpdate()
                ->get();

            foreach ($stocks as $stock) {
                if ($remainingToReserve <= 0) break;
                
                $available = $stock->quantity - $stock->reserved;
                $toReserve = min($available, $remainingToReserve);
                
                $stock->increment('reserved', $toReserve);
                $remainingToReserve -= $toReserve;
            }

            if ($remainingToReserve > 0) {
                Log::warning('StockReservation: لم يجد مخزون كافٍ للحجز بشكل كامل', [
                    'variant_id' => $item['variant_id'],
                    'unreserved_quantity' => $remainingToReserve,
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
            $remainingToRelease = $item->quantity;
            
            $stocks = Stock::withoutGlobalScopes()
                ->where('variant_id', $item->product_variant_id)
                ->where('reserved', '>', 0)
                ->lockForUpdate()
                ->get();
                
            foreach ($stocks as $stock) {
                if ($remainingToRelease <= 0) break;
                
                $toRelease = min($stock->reserved, $remainingToRelease);
                $stock->decrement('reserved', $toRelease);
                $remainingToRelease -= $toRelease;
            }
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
