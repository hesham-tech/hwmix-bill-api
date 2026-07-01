<?php
// تعريف مسارات واجهة برمجة التطبيقات (API) الخاصة بموديول بوابة الرسائل.

use Illuminate\Support\Facades\Route;
use Modules\SmsGateway\Http\Controllers\Api\v1\AgentAuthController;
use Modules\SmsGateway\Http\Controllers\Api\v1\AgentDeviceController;
use Modules\SmsGateway\Http\Controllers\Api\v1\AgentCommandController;
use Modules\SmsGateway\Http\Controllers\Api\v1\AgentSmsController;

Route::prefix('v1/agent')->group(function () {
    // مسارات عامة للمصادقة والتحديثات
    Route::post('auth/login', [AgentAuthController::class, 'login']);
    Route::get('public/app-update/check', [AgentDeviceController::class, 'checkAppUpdate']);
    
    // مسارات تتطلب مصادقة الـ Token
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('auth/refresh', [AgentAuthController::class, 'refresh']);
        
        // الأجهزة وإعداداتها (تطبيق الـ Idempotency للمحافظة على حالة واحدة للجهاز والنبضات)
        Route::middleware([\Modules\SmsGateway\Http\Middleware\IdempotencyMiddleware::class])->group(function () {
            Route::post('device/register', [AgentDeviceController::class, 'register']);
            Route::post('device/sync-lines', [AgentDeviceController::class, 'syncLines']);
        });

        // النبضات وسحب التكوينات غير الخاضعة للـ Idempotency لمرونتها المستمرة
        Route::post('device/heartbeat', [AgentDeviceController::class, 'heartbeat']);
        Route::get('device/config', [AgentDeviceController::class, 'config']);
        
        // الأوامر التشغيلية والـ SMS
        Route::get('commands/pending', [AgentCommandController::class, 'pending']);
        
        Route::middleware([\Modules\SmsGateway\Http\Middleware\IdempotencyMiddleware::class])->group(function () {
            Route::post('commands/{id}/execute', [AgentCommandController::class, 'execute']);
            Route::post('sms/incoming', [AgentSmsController::class, 'incoming']);
            Route::post('sms/sync-status', [AgentSmsController::class, 'syncStatus']);
            Route::post('sms/batch-sync', [AgentSmsController::class, 'batchSync']);
        });
    });
});

// مسارات لوحة التحكم بالويب (Vue UI API)
Route::middleware(['auth:sanctum'])->prefix('v1/sms-gateway')->group(function () {
    Route::get('devices', [\Modules\SmsGateway\Http\Controllers\Web\DeviceController::class, 'index']);
    Route::delete('devices/{id}', [\Modules\SmsGateway\Http\Controllers\Web\DeviceController::class, 'destroy']);
    
    Route::get('lines', [\Modules\SmsGateway\Http\Controllers\Web\LineController::class, 'index']);
    
    Route::get('messages', [\Modules\SmsGateway\Http\Controllers\Web\MessageController::class, 'index']);
    Route::post('messages/send', [\Modules\SmsGateway\Http\Controllers\Web\MessageController::class, 'store']);
});
