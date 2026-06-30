<?php
// كلاس مسؤول عن معالجة وإدارة متغيرات المنتجات وربطها بالسمات والمخازن والوحدات والأسعار المخصصة
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
            // نسخ وحدات المنتج الأب إلى الـ variant إذا لم تكن محددة
            $variantArray = $variantData->toArray();
            if (empty($variantArray['base_unit_id'])     && $product->base_unit_id)     { $variantArray['base_unit_id']     = $product->base_unit_id; }
            if (empty($variantArray['purchase_unit_id']) && $product->purchase_unit_id) { $variantArray['purchase_unit_id'] = $product->purchase_unit_id; }
            if (empty($variantArray['display_unit_id'])  && $product->display_unit_id)  { $variantArray['display_unit_id']  = $product->display_unit_id; }

            $variant = $product->variants()->updateOrCreate(
                ['id' => $variantData->id ?? null],
                array_merge($variantArray, [
                    'company_id' => $companyId,
                    'created_by' => $user->id,
                ])
            );

            if (isset($variantData->image_ids)) {
                $variant->syncImages($variantData->image_ids, 'gallery', $variantData->primary_image_id ?? null);
            }

            $this->syncAttributes($variant, $variantData->attributes ?? [], $companyId, $user->id);
            $this->syncStocks($variant, $variantData->stocks ?? [], $companyId, $user->id);
            $this->syncVariantUnits($variant, $variantData->units ?? []);
            $this->syncVariantUnitPrices($variant, $variantData->unit_prices ?? []);
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

    protected function syncVariantUnits($variant, array $units): void
    {
        $variant->units()->delete();
        foreach ($units as $unitData) {
            $variant->units()->create([
                'unit_id' => $unitData['unit_id'],
                'conversion_factor_to_base' => $unitData['conversion_factor_to_base'],
                'is_default' => $unitData['is_default'] ?? false,
                'min_quantity' => $unitData['min_quantity'] ?? null,
                'max_quantity' => $unitData['max_quantity'] ?? null,
                'allow_fraction' => $unitData['allow_fraction'] ?? false,
            ]);
        }
    }

    protected function syncVariantUnitPrices($variant, array $prices): void
    {
        $variant->unitPrices()->delete();
        foreach ($prices as $priceData) {
            $variant->unitPrices()->create([
                'unit_id' => $priceData['unit_id'],
                'price' => $priceData['price'],
                'cost' => $priceData['cost'] ?? null,
                'effective_from' => $priceData['effective_from'] ?? null,
                'effective_to' => $priceData['effective_to'] ?? null,
                'is_default' => $priceData['is_default'] ?? true,
            ]);
        }
    }
}
