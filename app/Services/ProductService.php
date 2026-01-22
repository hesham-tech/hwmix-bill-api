<?php

namespace App\Services;

use App\DTOs\ProductData;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductService
{
    /**
     * Create a new product with its variants and stocks.
     */
    public function createProduct(ProductData $data, User $user, int $companyId): Product
    {
        return DB::transaction(function () use ($data, $user, $companyId) {
            $productData = $data->toArray();
            $productData['company_id'] = $companyId;
            $productData['created_by'] = $user->id;
            $productData['slug'] = Product::generateSlug($data->name);

            // Handle digital keys count
            if ($data->product_type === Product::TYPE_DIGITAL && !empty($data->license_keys)) {
                $productData['available_keys_count'] = count($data->license_keys);
            }

            $product = Product::create($productData);

            // Sync Product Images
            if (!empty($data->image_ids)) {
                $product->syncImages($data->image_ids);
            }

            $this->handleVariants($product, $data->variants, $user, $companyId);

            return $product;
        });
    }

    /**
     * Update an existing product.
     */
    public function updateProduct(Product $product, ProductData $data, User $user): Product
    {
        return DB::transaction(function () use ($product, $data, $user) {
            $companyId = $product->company_id;
            $updateData = $data->toArray();

            // Handle digital keys count
            if ($data->product_type === Product::TYPE_DIGITAL && isset($data->license_keys)) {
                $updateData['available_keys_count'] = count($data->license_keys);
            }

            $product->update($updateData);

            // Sync Product Images
            if (isset($data->image_ids)) {
                $product->syncImages($data->image_ids);
            }

            $this->handleVariants($product, $data->variants, $user, $companyId);

            return $product->load(['variants.stocks', 'variants.attributes']);
        });
    }

    /**
     * Handle creation and update of product variants.
     */
    protected function handleVariants(Product $product, array $variantsData, User $user, int $companyId): void
    {
        // If updating, handle deletions
        if ($product->wasRecentlyCreated === false) {
            $requestedIds = collect($variantsData)->pluck('id')->filter()->all();
            $product->variants()->whereNotIn('id', $requestedIds)->each(function ($variant) {
                $variant->attributes()->delete();
                $variant->stocks()->delete();
                $variant->delete();
            });
        }

        foreach ($variantsData as $variantData) {
            $variant = $product->variants()->updateOrCreate(
                ['id' => $variantData->id],
                array_merge($variantData->toArray(), [
                    'company_id' => $companyId,
                    'created_by' => $user->id,
                ])
            );

            // Sync Variant Images
            if (isset($variantData->image_ids)) {
                $variant->syncImages($variantData->image_ids, 'gallery', $variantData->primary_image_id);
            }

            // Sync Attributes
            $variant->attributes()->delete();
            foreach ($variantData->attributes as $attr) {
                $variant->attributes()->create([
                    'attribute_id' => $attr['attribute_id'] ?? $attr['id'],
                    'attribute_value_id' => $attr['attribute_value_id'] ?? ($attr['value_id'] ?? null),
                    'company_id' => $companyId,
                    'created_by' => $user->id,
                ]);
            }

            // Sync Stocks
            if ($variant->wasRecentlyCreated === false) {
                $requestedStockIds = collect($variantData->stocks)->pluck('id')->filter()->all();
                $variant->stocks()->whereNotIn('id', $requestedStockIds)->delete();
            }

            foreach ($variantData->stocks as $stockData) {
                $variant->stocks()->updateOrCreate(
                    ['id' => $stockData->id],
                    array_merge($stockData->toArray(), [
                        'company_id' => $companyId,
                        'created_by' => $user->id,
                    ])
                );
            }
        }
    }
}
