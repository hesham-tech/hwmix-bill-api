<?php

use Illuminate\Support\Facades\Route;
use Modules\DigitalServices\Http\Controllers\ProviderManagementController;
use Modules\DigitalServices\Http\Controllers\QuickServiceController;
use Modules\DigitalServices\Http\Controllers\ReversalServiceController;
use Modules\DigitalServices\Http\Controllers\ServiceProviderController;
use Modules\DigitalServices\Http\Controllers\ServiceDefinitionController;

Route::middleware(['auth:sanctum'])->prefix('v1/digital-services')->group(function () {
    // إعدادات شبكات الدفع الأساسية (مثل فوري، أمان)
    Route::get('/service-providers', [ServiceProviderController::class, 'index']);
    Route::post('/service-providers', [ServiceProviderController::class, 'store']);
    Route::put('/service-providers/{id}', [ServiceProviderController::class, 'update']);
    Route::delete('/service-providers/{id}', [ServiceProviderController::class, 'destroy']);

    // إعدادات الخدمات التي تقدمها الشبكات (مثل إيداع، سحب)
    Route::get('/service-definitions', [ServiceDefinitionController::class, 'index']);
    Route::post('/service-definitions', [ServiceDefinitionController::class, 'store']);
    Route::put('/service-definitions/{id}', [ServiceDefinitionController::class, 'update']);
    Route::delete('/service-definitions/{id}', [ServiceDefinitionController::class, 'destroy']);

    // إعدادات الماكينات والمحافظ (ربط الشبكات بخزائن)
    Route::get('/providers', [ProviderManagementController::class, 'index']);
    Route::post('/providers', [ProviderManagementController::class, 'store']);
    
    // تسجيل عمليات الكاشير
    Route::post('/transactions', [QuickServiceController::class, 'store']);
    
    // عكس العملية (Reversal)
    Route::post('/transactions/{id}/reverse', [ReversalServiceController::class, 'reverse']);
});
