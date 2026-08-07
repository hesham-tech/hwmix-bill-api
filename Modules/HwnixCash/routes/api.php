<?php
// مسارات الـ API الخاصة بموديول كاش هونكس HwnixCash لـ Agent الأندرويد ولوحة التحكم.

use Illuminate\Support\Facades\Route;
use Modules\HwnixCash\Http\Controllers\Api\v1\AgentAuthController;
use Modules\HwnixCash\Http\Controllers\Api\v1\AgentDeviceController;
use Modules\HwnixCash\Http\Controllers\Api\v1\AgentCommandController;
use Modules\HwnixCash\Http\Controllers\Api\v1\AgentSmsController;
use Modules\HwnixCash\Http\Controllers\Api\v1\AgentOnboardingController;

Route::prefix('v1/agent')->group(function () {
    // مسارات عامة للمصادقة والتحديثات
    Route::post('auth/login', [AgentAuthController::class, 'login']);
    Route::post('auth/register', [AgentAuthController::class, 'register']);
    Route::get('public/app-update/check', [AgentDeviceController::class, 'checkAppUpdate']);
    
    // مسارات تتطلب مصادقة الـ Token
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('auth/refresh', [AgentAuthController::class, 'refresh']);
        Route::get('companies', [AgentAuthController::class, 'getCompanies']);
        
        // مسارات التهيئة (Onboarding) تعتمد على سياق الشركة
        Route::middleware([\App\Http\Middleware\MobileCompanyContextMiddleware::class])->group(function () {
            Route::get('wallets', [AgentOnboardingController::class, 'getWallets']);
            Route::post('validate-onboarding', [AgentOnboardingController::class, 'validateField']);
            
            Route::middleware([\Modules\HwnixCash\Http\Middleware\IdempotencyMiddleware::class])->group(function () {
                Route::post('onboarding/complete', [AgentOnboardingController::class, 'completeOnboarding']);
            });
        });
        
        // الأجهزة وإعداداتها (تطبيق الـ Idempotency)
        Route::middleware([\Modules\HwnixCash\Http\Middleware\IdempotencyMiddleware::class])->group(function () {
            Route::post('device/register', [AgentDeviceController::class, 'register']);
            Route::post('device/sync-lines', [AgentDeviceController::class, 'syncLines']);
        });

        // النبضات وسحب التكوينات غير الخاضعة للـ Idempotency لمرونتها المستمرة
        Route::post('device/heartbeat', [AgentDeviceController::class, 'heartbeat']);
        Route::get('device/config', [AgentDeviceController::class, 'getConfig']);
        Route::get('device/lines', [AgentDeviceController::class, 'getLines']);
        Route::post('device/decouple', [AgentDeviceController::class, 'decouple']);
        Route::post('device/log', [AgentDeviceController::class, 'log']);
        
        // الأوامر التشغيلية والـ SMS
        Route::get('commands/pending', [AgentCommandController::class, 'getPendingCommands']);
        
        Route::middleware([\Modules\HwnixCash\Http\Middleware\IdempotencyMiddleware::class])->group(function () {
            Route::post('commands/{id}/execute', [AgentCommandController::class, 'execute']);
            Route::post('sms/incoming', [AgentSmsController::class, 'incoming']);
            Route::post('sms/sync-status', [AgentSmsController::class, 'syncStatus']);
            Route::post('sms/batch-sync', [AgentSmsController::class, 'batchSync']);
        });
    });
});

// مسارات لوحة التحكم بالويب (Vue UI API لكاش هونكس)
Route::middleware(['auth:sanctum'])->prefix('v1/hwnix-cash')->group(function () {
    Route::get('devices', [\Modules\HwnixCash\Http\Controllers\Web\DeviceController::class, 'index']);
    Route::delete('devices/{id}', [\Modules\HwnixCash\Http\Controllers\Web\DeviceController::class, 'destroy']);
    
    Route::get('lines', [\Modules\HwnixCash\Http\Controllers\Web\LineController::class, 'index']);
    Route::put('lines/{id}', [\Modules\HwnixCash\Http\Controllers\Web\LineController::class, 'update']);
    Route::post('lines/{id}/reconcile', [\Modules\HwnixCash\Http\Controllers\Web\LineController::class, 'reconcile']);
    
    Route::get('messages', [\Modules\HwnixCash\Http\Controllers\Web\MessageController::class, 'index']);
    Route::post('messages/send', [\Modules\HwnixCash\Http\Controllers\Web\MessageController::class, 'store']);
    Route::post('messages/{id}/reparse', [\Modules\HwnixCash\Http\Controllers\Web\MessageController::class, 'reparse']);

    // مسارات إدارة معاملات المحافظ الإلكترونية
    Route::apiResource('wallet-transactions', \Modules\HwnixCash\Http\Controllers\Web\WalletTransactionController::class);

    // مسارات إدارة مصادر الرسائل المعتمدة
    Route::apiResource('message-sources', \Modules\HwnixCash\Http\Controllers\Web\MessageSourceController::class);

    // مسارات إدارة الحسابات المالية (Financial Accounts)
    Route::get('financial-accounts/limit-alerts', [\Modules\HwnixCash\Http\Controllers\Web\FinancialAccountController::class, 'limitAlerts']);
    Route::get('financial-accounts/distinct-senders', [\Modules\HwnixCash\Http\Controllers\Web\FinancialAccountController::class, 'distinctSenders']);
    Route::post('financial-accounts/{id}/reconcile', [\Modules\HwnixCash\Http\Controllers\Web\FinancialAccountController::class, 'reconcile']);
    Route::apiResource('financial-accounts', \Modules\HwnixCash\Http\Controllers\Web\FinancialAccountController::class);
});
