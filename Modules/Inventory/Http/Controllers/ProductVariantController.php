<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group إدارة المتغيرات (Product Variants)
 */
class ProductVariantController extends Controller
{
    /**
     * بحث متقدم في التشكيلات
     * 
     * @queryParam search string الكلمة البحثية
     * @queryParam has_stock int 1 لإظهار المتوفر فقط, 0 لإظهار الكل
     * @queryParam in_sales int 1 لإظهار المتاح للمبيعات فقط
     */
    public function searchByProduct(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $hasStock = $request->get('has_stock', 1);
            $inSales = $request->get('in_sales');

            $query = ProductVariant::with([
                'product', 'images', 'attributes.attributeValue', 'stocks'
            ]);

            // فلترة بناء على حالة المنتج (في المبيعات أو المتجر)
            $query->whereHas('product', function($q) use ($inSales) {
                if ($inSales) {
                    $q->inSales();
                } else {
                    $q->where('active', true);
                }
            });

            // فلترة البحث النصي
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%")
                      ->orWhereHas('product', function($pq) use ($search) {
                          $pq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $variants = $query->take(30)->get();

            // تهيئة البيانات للواجهة الأمامية
            $data = $variants->map(function ($variant) use ($hasStock) {
                $product = $variant->product;
                
                if (!$product) return null;

                $quantity = $product->require_stock ? $variant->stocks->where('status', 'available')->sum('quantity') : 999999;
                
                // تجاهل المتغيرات غير المتوفرة إذا كان مطلوباً
                if ($hasStock && $product->require_stock && $quantity <= 0) {
                    return null;
                }

                return [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $product->name,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'price' => $variant->retail_price,
                    'retail_price' => $variant->retail_price,
                    'wholesale_price' => $variant->wholesale_price,
                    'purchase_price' => $variant->purchase_price,
                    'quantity' => $quantity,
                    'requires_stock' => $product->require_stock,
                    'product_type' => $product->product_type,
                    'primary_image_url' => $variant->primary_image_url,
                    'attributes' => $variant->attributes->map(function($attr) {
                        return [
                            'attribute_value' => [
                                'name' => $attr->attributeValue->name ?? null
                            ]
                        ];
                    })
                ];
            })->filter()->values();

            return api_success($data);

        } catch (\Throwable $e) {
            Log::error('Search by product error: ' . $e->getMessage());
            return api_exception($e);
        }
    }
}
