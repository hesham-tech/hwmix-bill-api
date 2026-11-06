<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentGatewayController;
use Modules\Payment\Http\Controllers\PaymentController;

//   مسارات الـ API لموديول الدفع الإلكتروني (بوابات الدفع والعمليات والـ Webhooks).

// 1. مسارات محمية بصلاحيات تسجيل الدخول
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::middleware(\Modules\Payment\Http\Middleware\ManagePaymentGateways::class)->group(function () {
        Route::get('payment-gateway-drivers', function () {
            return api_success(collect(\Modules\Payment\Services\GatewayRegistry::definitions())->map(fn($definition,$id)=>[
                'id'=>$id,'name'=>$definition['name'],'fields'=>$definition['fields'],'required'=>$definition['required'],'capabilities'=>$definition['capabilities'],
                'webhook_url'=>route('api.payment.webhook',['driver'=>$id]),'callback_url'=>route('api.payment.callback',['driver'=>$id]),
            ])->values());
        });
        Route::patch('payment-gateways/{id}/set-default', [PaymentGatewayController::class, 'setDefault'])->name('payment-gateways.set-default');
        Route::apiResource('payment-gateways', PaymentGatewayController::class)->names('payment-gateways');
    });
    Route::post('payments/process', [PaymentController::class, 'process'])->name('payment.process');
});

// 2. مسارات عامة لبوابات الدفع (Webhooks & Callbacks)
Route::prefix('v1')->group(function () {
    Route::post('payments/webhook/{driver}', [PaymentController::class, 'webhook'])->name('payment.webhook');
    Route::get('payments/callback/{driver}', [PaymentController::class, 'callback'])->name('payment.callback');
});
