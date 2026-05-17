<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;
use Modules\Inventory\Http\Controllers\WarehouseController;
use Modules\Inventory\Http\Controllers\BrandController;
use Modules\Inventory\Http\Controllers\CategoryController;
use Modules\Inventory\Http\Controllers\ProductController;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\AttributeController;
use Modules\Inventory\Http\Controllers\AttributeValueController;

Route::middleware(['auth:sanctum', 'scope_company', 'branch_context', 'throttle:api'])->prefix('v1')->group(function () {
    // متغيرات المنتجات (Products Variants Search)
    Route::get('product-variants/search-by-product', [\Modules\Inventory\Http\Controllers\ProductVariantController::class, 'searchByProduct']);

    Route::apiResource('inventories', InventoryController::class)->names('inventory');
    
    // المستودعات
    Route::apiResource('warehouses', WarehouseController::class);
    Route::patch('warehouses/{warehouse}/set-default', [WarehouseController::class, 'setDefault']);

    // العلامات التجارية
    Route::apiResource('brands', BrandController::class);
    Route::patch('brands/{brand}/toggle', [BrandController::class, 'toggle']);

    // الأقسام
    Route::apiResource('categories', CategoryController::class);
    Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggle']);

    // المنتجات
    Route::apiResource('products', ProductController::class);

    // السمات
    Route::apiResource('attributes', AttributeController::class);
    Route::patch('attributes/{attribute}/toggle', [AttributeController::class, 'toggle']);
    Route::apiResource('attribute-values', AttributeValueController::class);

    // المخزون
    Route::apiResource('stocks', StockController::class);
});
