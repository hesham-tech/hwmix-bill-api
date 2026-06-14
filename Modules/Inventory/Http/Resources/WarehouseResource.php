<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserBasicResource;

use App\Http\Resources\Company\CompanyResource;

// محول بيانات المستودع إلى صيغة JSON مع الإحصائيات والتقارير الفورية للمخزون
class WarehouseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        $stocks = $this->relationLoaded('stocks') ? $this->stocks : collect();

        $totalItems = 0;
        $totalUniqueItems = 0;
        $totalWholesaleValue = 0;
        $totalRetailValue = 0;
        $totalCostValue = 0;
        $expiredItemsCount = 0;
        $expiringSoonItemsCount = 0;
        $lowStockItemsCount = 0;

        $now = now();
        $thirtyDaysFromNow = now()->addDays(30);
        $uniqueActiveVariants = [];

        foreach ($stocks as $stock) {
            $qty = (int)$stock->quantity;

            // حساب منتهية الصلاحية والتي تنتهي قريباً
            if ($stock->expiry) {
                $expiryDate = \Carbon\Carbon::parse($stock->expiry);
                if ($expiryDate->lt($now)) {
                    $expiredItemsCount += $qty;
                } elseif ($expiryDate->between($now, $thirtyDaysFromNow)) {
                    $expiringSoonItemsCount += $qty;
                }
            }

            // حساب النواقص
            $minQty = $stock->min_quantity ?? ($stock->variant?->min_quantity ?? 0);
            if ($qty <= $minQty) {
                $lowStockItemsCount++;
            }

            if ($qty <= 0) {
                continue;
            }

            $totalItems += $qty;
            if ($stock->variant_id) {
                $uniqueActiveVariants[$stock->variant_id] = true;
            }

            $variant = $stock->variant;
            if ($variant) {
                $wholesalePrice = (float)($variant->wholesale_price ?? 0);
                $retailPrice = (float)($variant->retail_price ?? 0);
                $totalWholesaleValue += $qty * $wholesalePrice;
                $totalRetailValue += $qty * $retailPrice;
            }

            $cost = (float)($stock->cost ?? ($variant->purchase_price ?? 0));
            $totalCostValue += $qty * $cost;
        }

        $totalUniqueItems = count($uniqueActiveVariants);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'manager' => $this->manager,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'is_default' => $this->is_default,
            'description' => $this->description,
            
            // إحصائيات المخزن الفورية
            'total_items' => $totalItems,
            'total_unique_items' => $totalUniqueItems,
            'total_wholesale_value' => $totalWholesaleValue,
            'total_retail_value' => $totalRetailValue,
            'total_cost_value' => $totalCostValue,
            'expired_items_count' => $expiredItemsCount,
            'expiring_soon_items_count' => $expiringSoonItemsCount,
            'low_stock_items_count' => $lowStockItemsCount,

            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'stocks' => $this->whenLoaded('stocks', fn() => StockResource::collection($this->stocks)),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
