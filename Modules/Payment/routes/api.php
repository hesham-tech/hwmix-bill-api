<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentGatewayController;
use Modules\Payment\Http\Controllers\PaymentController;

// تعليق عربي: مسارات الـ API لموديول الدفع الإلكتروني (بوابات الدفع والعمليات والـ Webhooks).

// 1. مسارات محمية بصلاحيات تسجيل الدخول
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('payment-gateways', PaymentGatewayController::class)->names('payment-gateways');
    Route::post('payments/process', [PaymentController::class, 'process'])->name('payment.process');
});

// 2. مسارات عامة لبوابات الدفع (Webhooks & Callbacks)
Route::prefix('v1')->group(function () {
    Route::post('payments/webhook/{driver}', [PaymentController::class, 'webhook'])->name('payment.webhook');
    Route::get('payments/callback/{driver}', [PaymentController::class, 'callback'])->name('payment.callback');
});
