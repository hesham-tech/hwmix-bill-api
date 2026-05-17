<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\DTOs\ProductData;
use Modules\Inventory\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Actions\Product\SyncProductImages;
use Modules\Inventory\Actions\Product\HandleProductVariants;
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

            if ($data->product_type === Product::TYPE_DIGITAL && !empty($data->license_keys)) {
                $productData['available_keys_count'] = count($data->license_keys);
            }

            $product = Product::create($productData);

            if (!empty($data->image_ids)) {
                $this->syncProductImages->execute($product, $data->image_ids);
            }

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

            if ($data->product_type === Product::TYPE_DIGITAL && isset($data->license_keys)) {
                $updateData['available_keys_count'] = count($data->license_keys);
            }

            $product->update($updateData);

            if (isset($data->image_ids)) {
                $this->syncProductImages->execute($product, $data->image_ids);
            }

            $this->handleProductVariants->execute($product, $data->variants, $user, $companyId);

            return $product;
        });
    }
}
