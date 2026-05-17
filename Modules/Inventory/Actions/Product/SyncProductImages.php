<?php

namespace Modules\Inventory\Actions\Product;

use Modules\Inventory\Models\Product;

class SyncProductImages
{
    /**
     * Sync images for a product.
     */
    public function execute(Product $product, array $imageIds): void
    {
        if (empty($imageIds)) {
            return;
        }

        $product->syncImages($imageIds);
    }
}
