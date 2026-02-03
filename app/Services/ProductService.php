<?php

namespace App\Services;

use App\DTOs\ProductData;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Actions\Product\SyncProductImages;
use App\Actions\Product\HandleProductVariants;
use Throwable;

class ProductService
{
    protected SyncProductImages $syncProductImages;
    protected HandleProductVariants $handleProductVariants;

    public function __construct(
        SyncProductImages $syncProductImages,
        HandleProductVariants $handleProductVariants
    ) {
        $this->syncProductImages = $syncProductImages;
        $this->handleProductVariants = $handleProductVariants;
    }

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

            // Action: Sync Product Images
            if (!empty($data->image_ids)) {
                $this->syncProductImages->execute($product, $data->image_ids);
            }

            // Action: Handle Variants
            $this->handleProductVariants->execute($product, $data->variants, $user, $companyId);

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

            // Action: Sync Product Images
            if (isset($data->image_ids)) {
                $this->syncProductImages->execute($product, $data->image_ids);
            }

            // Action: Handle Variants
            $this->handleProductVariants->execute($product, $data->variants, $user, $companyId);

            return $product;
        });
    }
}
