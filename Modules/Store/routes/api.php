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
        Route::get('/registration-legal', fn()=>response()->json(['success'=>true,'data'=>app(\Modules\Store\Services\StoreRegistrationLegalService::class)->documents()]));
        Route::get('/checkout-config', [StoreOrderController::class, 'configuration']);
        Route::get('/legal/{id}', [StoreOrderController::class, 'legal']);
        Route::get('/products', [StoreProductController::class, 'index']);
        Route::get('/products/featured', [StoreProductController::class, 'featured']);
        Route::get('/products/{slug}', [StoreProductController::class, 'show'])->whereNumber('slug');
        
        Route::get('/categories', [StoreProductController::class, 'categories']);
        Route::get('/brands', [StoreProductController::class, 'brands']);
        
        Route::get('/vendors', [VendorController::class, 'index']);
        Route::get('/vendors/{id}', [VendorController::class, 'show']);
        Route::get('/vendors/{id}/products', [VendorController::class, 'products']);
    });

    // Authenticated Customer Routes (and mixed)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/account/notifications',[\Modules\Store\Http\Controllers\StoreNotificationController::class,'index']);
        Route::post('/account/notifications/{id}/read',[\Modules\Store\Http\Controllers\StoreNotificationController::class,'read'])->whereNumber('id');
        Route::get('/account/status', [\Modules\Store\Http\Controllers\StoreAccountController::class, 'status']);
        Route::post('/account/email/send', [\Modules\Store\Http\Controllers\StoreAccountController::class, 'send'])->middleware('throttle:5,60');
        Route::post('/account/email/verify', [\Modules\Store\Http\Controllers\StoreAccountController::class, 'verify'])->middleware('throttle:10,1');
        Route::post('/addresses/{id}/default', [CustomerAddressController::class, 'setDefault']);
        Route::apiResource('/addresses', CustomerAddressController::class)->except('show');
        
        // المفضلة
        Route::get('/wishlist', [StoreWishlistController::class, 'index']);
        Route::get('/wishlist/ids', [StoreWishlistController::class, 'getIds']);
        Route::post('/wishlist/toggle', [StoreWishlistController::class, 'toggle']);
    });

    // Orders (Mixed: Guest & Auth)
    Route::post('/cart/validate',[StoreProductController::class,'validateCart'])->middleware('throttle:30,1');
    Route::get('/orders', [StoreOrderController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/checkout/quote', [StoreOrderController::class, 'quote'])->middleware('throttle:30,1');
    Route::post('/orders', [StoreOrderController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/orders/{id}', [StoreOrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [StoreOrderController::class, 'cancel'])->middleware('throttle:20,1');
    Route::post('/orders/{id}/payment', [StoreOrderController::class, 'pay'])->middleware('throttle:10,1');
    Route::get('/orders/{id}/returns',[StoreOrderController::class,'returns']);
    Route::post('/orders/{id}/returns',[StoreOrderController::class,'requestReturn'])->middleware('throttle:10,1');
});

// Authenticated Vendor (Staff) Routes
Route::prefix('vendor/store')->middleware('auth:sanctum')->group(function () {
    Route::get('/notifications',[\Modules\Store\Http\Controllers\StoreNotificationController::class,'index'])->defaults('scope','vendor');
    Route::post('/notifications/{id}/read',[\Modules\Store\Http\Controllers\StoreNotificationController::class,'read'])->defaults('scope','vendor')->whereNumber('id');
    Route::get('/account', [VendorOrderController::class, 'account']);
    Route::post('/payouts',[VendorOrderController::class,'requestPayout'])->middleware('throttle:10,1');
    Route::get('/orders', [VendorOrderController::class, 'index']);
    Route::get('/orders/{id}', [VendorOrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [VendorOrderController::class, 'updateStatus']);
});

Route::prefix('admin/store')->middleware('auth:sanctum')->group(function () {
    Route::get('/notifications',[\Modules\Store\Http\Controllers\StoreNotificationController::class,'index'])->defaults('scope','admin');
    Route::post('/notifications/{id}/read',[\Modules\Store\Http\Controllers\StoreNotificationController::class,'read'])->defaults('scope','admin')->whereNumber('id');
    $controller=\Modules\Store\Http\Controllers\StoreAdminController::class;
    Route::get('/gateway-settlements',[$controller,'gatewaySettlements']);
    Route::post('/gateway-settlements',[$controller,'recordGatewaySettlement'])->middleware('throttle:20,1');
    Route::post('/sub-orders/{id}/rto',[$controller,'rto'])->middleware('throttle:20,1');
    Route::get('/bootstrap',[$controller,'bootstrap']);
    Route::put('/settings',[$controller,'settings']);
    Route::get('/sellers/{id}',[$controller,'seller']);
    Route::put('/sellers/{id}',[$controller,'saveSeller']);
    Route::get('/shipping-accounts',[$controller,'shippingAccounts']);
    Route::get('/shipping-drivers',function() {
        app(\Modules\Store\Services\StoreAccess::class)->platform();
        return response()->json(['data'=>collect(\Modules\Store\Services\Shipping\ShippingGatewayRegistry::definitions())->map(fn($d,$id)=>['id'=>$id,'name'=>$d['name'],'fields'=>$d['fields'],'automatic'=>$d['automatic']])->values()]);
    });
    Route::post('/shipping-accounts',[$controller,'saveShippingAccount']);
    Route::put('/shipping-accounts/{id}',[$controller,'saveShippingAccount']);
    Route::get('/shipping-accounts/{id}/rates',[$controller,'rates']);
    Route::post('/shipping-accounts/{id}/rates',[$controller,'saveRate']);
    Route::get('/orders',[$controller,'orders']);
    Route::get('/orders/{id}',[$controller,'order']);
    Route::post('/orders/{id}/confirm',[$controller,'confirm']);
    Route::patch('/sub-orders/{id}/status',[$controller,'transition']);
    Route::post('/shipments/{id}/dispatch',[$controller,'dispatch']);
    Route::post('/shipments/{id}/cancel',[$controller,'cancelShipment']);
    Route::get('/accounts',[$controller,'accounts']);
    Route::get('/payouts',[$controller,'payouts']);
    Route::get('/payments',[$controller,'payments']);
    Route::post('/payments/{id}/reconcile',[$controller,'reconcilePayment']);
    Route::get('/returns',[$controller,'returns']);
    Route::post('/returns/{id}/action',[$controller,'updateReturn']);
    Route::get('/refunds',[$controller,'refunds']);
    Route::post('/refunds/{id}/action',[$controller,'updateRefund']);
    Route::post('/payouts',[$controller,'requestPayout']);
    Route::get('/payouts/{id}',[$controller,'payout']);
    Route::post('/payouts/{id}/action',[$controller,'updatePayout']);
    Route::get('/accounts/{id}/ledger',[$controller,'ledger']);
    Route::post('/accounts/{id}/topup',[$controller,'topup']);
    Route::post('/sub-orders/{id}/collection',[$controller,'collection']);
    Route::post('/sub-orders/{id}/remittance',[$controller,'remittance']);
    Route::get('/outbox',[$controller,'outbox']);
    Route::post('/outbox/{id}/retry',[$controller,'retry']);
});
