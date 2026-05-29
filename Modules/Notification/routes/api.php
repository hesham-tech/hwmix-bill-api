<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\NotificationController;
use Modules\Notification\Http\Controllers\MailSettingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // مسارات إدارة حسابات البريد الإلكتروني المتعددة للشركة
    Route::apiResource('mail-settings', MailSettingController::class)->names('mail-settings');
    Route::post('mail-settings/{id}/test', [MailSettingController::class, 'testConnection'])->name('mail-settings.test');
    Route::post('mail-settings/{id}/set-default', [MailSettingController::class, 'setDefault'])->name('mail-settings.set-default');

    Route::apiResource('notifications', NotificationController::class)->names('notification');
});

