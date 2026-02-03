<?php

namespace App\Actions\Product;

use App\Models\Product;
use App\Models\Image;

class SyncProductImages
{
    /**
     * Sync images for a product.
     * 
     * @param Product $product
     * @param array $imageIds
     * @return void
     */
    public function execute(Product $product, array $imageIds): void
    {
        if (empty($imageIds)) {
            return;
        }

        $product->syncImages($imageIds);
    }
}
