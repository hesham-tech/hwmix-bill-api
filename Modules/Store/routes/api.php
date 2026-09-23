<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\StoreProductController;
use Modules\Store\Http\Controllers\VendorController;
use Modules\Store\Http\Controllers\CustomerAddressController;
use Modules\Store\Http\Controllers\StoreOrderController;
use Modules\Store\Http\Controllers\VendorOrderController;
use Modules\Store\Http\Controllers\StoreWishlistController;
use Modules\Store\Http\Controllers\StoreSocialPreviewController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('store')->group(function() {
    
    // Social Media Bot Previews
    Route::get('/og-preview/store-index', [StoreSocialPreviewController::class, 'storeIndexPreview']);
    Route::get('/og-preview/{id}', [StoreSocialPreviewController::class, 'productPreview']);

    // Public Routes
    Route::prefix('public')->group(function() {
        Route::get('/products', [StoreProductController::class, 'index']);
        Route::get('/products/featured', [StoreProductController::class, 'featured']);
        Route::get('/products/{slug}', [StoreProductController::class, 'show']);
        
        Route::get('/categories', [StoreProductController::class, 'categories']);
        Route::get('/brands', [StoreProductController::class, 'brands']);
        
        Route::get('/vendors', [VendorController::class, 'index']);
        Route::get('/vendors/{id}', [VendorController::class, 'show']);
        Route::get('/vendors/{id}/products', [VendorController::class, 'products']);
    });

    // Authenticated Customer Routes (and mixed)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/addresses/{id}/default', [CustomerAddressController::class, 'setDefault']);
        Route::apiResource('/addresses', CustomerAddressController::class);
        
        // المفضلة
        Route::get('/wishlist', [StoreWishlistController::class, 'index']);
        Route::get('/wishlist/ids', [StoreWishlistController::class, 'getIds']);
        Route::post('/wishlist/toggle', [StoreWishlistController::class, 'toggle']);
    });

    // Orders (Mixed: Guest & Auth)
    Route::get('/orders', [StoreOrderController::class, 'index']);
    Route::post('/orders', [StoreOrderController::class, 'store']);
    Route::get('/orders/{id}', [StoreOrderController::class, 'show']);
});

// Authenticated Vendor (Staff) Routes
Route::prefix('vendor/store')->middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [VendorOrderController::class, 'index']);
    Route::get('/orders/{id}', [VendorOrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [VendorOrderController::class, 'updateStatus']);
});
