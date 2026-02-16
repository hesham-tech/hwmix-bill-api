<?php

namespace App\Actions\Product;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class HandleProductVariants
{
    /**
     * Handle creation and update of product variants.
     */
    public function execute(Product $product, array $variantsData, User $user, int $companyId): void
    {
        // 1. Handle Deletions (if updating)
        if (!$product->wasRecentlyCreated) {
            $requestedIds = collect($variantsData)->pluck('id')->filter()->all();
            $product->variants()->whereNotIn('id', $requestedIds)->each(function ($variant) {
                // Observers are triggered by individual delete/each
                $variant->attributes()->delete();
                $variant->stocks()->delete();
                $variant->delete();
            });
        }

        // 2. Handle Creation/Updates
        foreach ($variantsData as $variantData) {
            $variant = $product->variants()->updateOrCreate(
                ['id' => $variantData->id ?? null],
                array_merge($variantData->toArray(), [
                    'company_id' => $companyId,
                    'created_by' => $user->id,
                ])
            );

            // Sync Variant Images (if DTO supports it)
            if (isset($variantData->image_ids)) {
                $variant->syncImages($variantData->image_ids, 'gallery', $variantData->primary_image_id ?? null);
            }

            // Sync Attributes
            $this->syncAttributes($variant, $variantData->attributes ?? [], $companyId, $user->id);

            // Sync Stocks
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
            // Using DTO-like objects if passed, or arrays
            $data = is_array($stockData) ? $stockData : $stockData->toArray();

            // Search for existing stock by ID or by the variant/warehouse combination
            // to prevent duplicates for the same warehouse
            $variant->stocks()->updateOrCreate(
                [
                    'id' => $data['id'] ?? null,
                ],
                [
                    'id' => $data['id'] ?? null, // Explicitly pass id if it exists
                    'warehouse_id' => $data['warehouse_id'],
                    'quantity' => $data['quantity'] ?? 0,
                    'company_id' => $companyId,
                    'created_by' => $userId,
                ]
            );
        }
    }
}
