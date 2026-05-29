<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\InvoiceController;
use Modules\Sales\Http\Controllers\InvoiceTypeController;
use Modules\Sales\Http\Controllers\ServiceController;
use Modules\Sales\Http\Controllers\InvoiceItemController;
use Modules\Sales\Http\Controllers\SubscriptionController;
use Modules\Sales\Http\Controllers\SubscriptionRenewalController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Invoice Routes
    Route::controller(InvoiceController::class)->group(function () {
        Route::get('invoices', 'index');
        Route::post('invoices', 'store')->middleware('saas.limit:invoices');
        Route::get('invoices/{invoice}', 'show');
        Route::put('invoices/{invoice}', 'update');
        Route::delete('invoices/{invoice}', 'destroy');
        Route::get('invoice/{id}/pdf', 'downloadPDF')->name('invoice.download-pdf');
    });

    // InvoiceType Routes
    Route::controller(InvoiceTypeController::class)->group(function () {
        Route::get('invoice-types', 'index');
        Route::post('invoice-types', 'store');
        Route::get('invoice-types/{invoiceType}', 'show');
        Route::put('invoice-types/{invoiceType}', 'update');
        Route::delete('invoice-types/{invoiceType}', 'destroy');
    });

    // Service Routes
    Route::apiResource('services', ServiceController::class);

    // Subscription Routes
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::post('subscriptions/{id}/renew', [SubscriptionRenewalController::class, 'renew']);
    Route::get('subscriptions/{id}/history', [SubscriptionRenewalController::class, 'history']);

    // InvoiceItem Routes
    Route::controller(InvoiceItemController::class)->group(function () {
        Route::get('invoice-items', 'index');
        Route::post('invoice-item', 'store');
        Route::get('invoice-item/{invoiceItem}', 'show');
        Route::put('invoice-item/{invoiceItem}', 'update');
        Route::delete('invoice-item/{invoiceItem}', 'destroy');
    });
});
