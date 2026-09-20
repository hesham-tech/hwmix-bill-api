<?php

use Illuminate\Support\Facades\Route;
use Modules\DigitalServices\Http\Controllers\ProviderManagementController;
use Modules\DigitalServices\Http\Controllers\QuickServiceController;
use Modules\DigitalServices\Http\Controllers\ReversalServiceController;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('v1/digital-services')->group(function () {
    // إعدادات המאكينات والمحافظ
    Route::get('/providers', [ProviderManagementController::class, 'index']);
    Route::post('/providers', [ProviderManagementController::class, 'store']);
    
    // تسجيل عمليات الكاشير
    Route::post('/transactions', [QuickServiceController::class, 'store']);
    
    // عكس العملية (Reversal)
    Route::post('/transactions/{id}/reverse', [ReversalServiceController::class, 'reverse']);
});
