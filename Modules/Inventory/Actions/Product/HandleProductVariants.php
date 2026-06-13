<?php

namespace Modules\Inventory\Actions\Product;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Collection;

// معالجة وإدارة متغيرات المنتجات ومزامنة المخزون الخاص بها في المستودعات
class HandleProductVariants
{
    /**
     * Handle creation and update of product variants.
     */
    public function execute(Product $product, array $variantsData, User $user, int $companyId): void
    {
        if (!$product->wasRecentlyCreated) {
            $requestedIds = collect($variantsData)->pluck('id')->filter()->all();
            $product->variants()->whereNotIn('id', $requestedIds)->each(function ($variant) {
                $variant->attributes()->delete();
                $variant->stocks()->delete();
                $variant->delete();
            });
        }

        foreach ($variantsData as $variantData) {
            $variant = $product->variants()->updateOrCreate(
                ['id' => $variantData->id ?? null],
                array_merge($variantData->toArray(), [
                    'company_id' => $companyId,
                    'created_by' => $user->id,
                ])
            );

            if (isset($variantData->image_ids)) {
                $variant->syncImages($variantData->image_ids, 'gallery', $variantData->primary_image_id ?? null);
            }

            $this->syncAttributes($variant, $variantData->attributes ?? [], $companyId, $user->id);
            $this->syncStocks($variant, $variantData->stocks ?? [], $companyId, $user->id);
        }
    }

    protected function syncAttributes($variant, array $attributes, int $companyId, int $userId): void
    {
        $variant->attributes()->delete();
        foreach ($attributes as $attr) {
            $variant->attributes()->create([
                'attribute_id' => $attr['attribute_id'] ?? ($attr['id'] ?? null),
                'attribute_value_id' => $attr['attribute_value_id'] ?? ($attr['value_id'] ?? null),
                'company_id' => $companyId,
                'created_by' => $userId,
            ]);
        }
    }

    protected function syncStocks($variant, array $stocks, int $companyId, int $userId): void
    {
        if (!$variant->wasRecentlyCreated) {
            $requestedStockIds = collect($stocks)->pluck('id')->filter()->all();
            $variant->stocks()->whereNotIn('id', $requestedStockIds)->delete();
        }

        foreach ($stocks as $stockData) {
            $data = is_array($stockData) ? $stockData : $stockData->toArray();
            $warehouseId = $data['warehouse_id'] ?? null;

            if (!$warehouseId) {
                $warehouseId = Warehouse::where('company_id', $companyId)
                    ->orderBy('is_default', 'desc')
                    ->value('id');

                if (!$warehouseId) {
                    $newWarehouse = Warehouse::create([
                        'company_id' => $companyId,
                        'name' => 'المخزن الرئيسي',
                        'is_default' => true,
                        'created_by' => $userId,
                    ]);
                    $warehouseId = $newWarehouse->id;
                }
            }

            $stockId = $data['id'] ?? null;
            if (!$stockId) {
                $stockId = $variant->stocks()
                    ->where('warehouse_id', $warehouseId)
                    ->value('id');
            }

            $variant->stocks()->updateOrCreate(
                [
                    'id' => $stockId,
                ],
                [
                    'warehouse_id' => $warehouseId,
                    'quantity' => $data['quantity'] ?? 0,
                    'company_id' => $companyId,
                    'created_by' => $userId,
                ]
            );
        }
    }
}
