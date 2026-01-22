<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'iPhone 15 Pro Max - 256GB',
                'brand_id' => 1, // Apple
                'category_id' => 1, // موبايلات
                'cost' => 55000,
                'wholesale' => 58000,
                'retail' => 62000,
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra - 512GB',
                'brand_id' => 2, // Samsung
                'category_id' => 1, // موبايلات
                'cost' => 45000,
                'wholesale' => 48000,
                'retail' => 52000,
            ],
        ];

        foreach ($products as $index => $pData) {
            $product = Product::create([
                'name' => $pData['name'],
                'slug' => \Illuminate\Support\Str::slug($pData['name']),
                'active' => true,
                'featured' => true,
                'returnable' => true,
                'published_at' => now(),
                'desc' => 'أحدث إصدار من ' . $pData['name'],
                'desc_long' => 'تفاصيل كاملة عن ' . $pData['name'] . ' مع الضمان المحلي.',
                'company_id' => 1,
                'category_id' => $pData['category_id'],
                'brand_id' => $pData['brand_id'],
                'created_by' => 1,
            ]);

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'barcode' => 'BR-' . (100000 + $index),
                'sku' => 'SKU-' . (1000 + $index),
                'wholesale_price' => $pData['wholesale'],
                'retail_price' => $pData['retail'],
                'profit_margin' => ($pData['retail'] - $pData['cost']) / $pData['cost'],
                'status' => 'active',
                'company_id' => 1,
                'created_by' => 1,
            ]);

            Stock::create([
                'variant_id' => $variant->id,
                'warehouse_id' => 1,
                'company_id' => 1,
                'created_by' => 1,
                'quantity' => 50,
                'reserved' => 0,
                'min_quantity' => 5,
                'cost' => $pData['cost'],
                'batch' => 'BATCH-' . date('Ymd'),
                'status' => 'available',
            ]);
        }
    }
}
