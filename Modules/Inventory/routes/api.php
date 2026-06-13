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
    Route::post('product-variants/delete', [\Modules\Inventory\Http\Controllers\ProductVariantController::class, 'deleteMultiple']);
    Route::apiResource('product-variants', \Modules\Inventory\Http\Controllers\ProductVariantController::class);

    Route::apiResource('inventories', InventoryController::class)->names('inventory');
    
    // المستودعات
    Route::post('warehouses', [WarehouseController::class, 'store'])->middleware('saas.limit:warehouses');
    Route::apiResource('warehouses', WarehouseController::class)->except(['store']);
    Route::patch('warehouses/{warehouse}/set-default', [WarehouseController::class, 'setDefault']);

    // العلامات التجارية
    Route::apiResource('brands', BrandController::class);
    Route::patch('brands/{brand}/toggle', [BrandController::class, 'toggle']);

    // الأقسام
    Route::apiResource('categories', CategoryController::class);
    Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggle']);

    // المنتجات
    Route::post('products', [ProductController::class, 'store'])->middleware('saas.limit:products');
    Route::apiResource('products', ProductController::class)->except(['store']);

    // السمات
    Route::apiResource('attributes', AttributeController::class);
    Route::patch('attributes/{attribute}/toggle', [AttributeController::class, 'toggle']);
    Route::apiResource('attribute-values', AttributeValueController::class);

    // المخزون
    Route::apiResource('stocks', StockController::class);

    // وحدات القياس والمجموعات والتحويلات
    Route::apiResource('unit-groups', \Modules\Inventory\Http\Controllers\UnitGroupController::class);
    Route::apiResource('units', \Modules\Inventory\Http\Controllers\UnitController::class);
    Route::apiResource('unit-conversions', \Modules\Inventory\Http\Controllers\UnitConversionController::class);
});
