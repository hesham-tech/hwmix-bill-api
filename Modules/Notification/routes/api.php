<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\NotificationController;
use Modules\Notification\Http\Controllers\MailSettingController;
use Modules\Notification\Http\Controllers\WhatsAppSettingController;
use Modules\Notification\Http\Controllers\NotificationTemplateController;
use Modules\Notification\Http\Controllers\NotificationWorkflowController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // مسارات إدارة حسابات البريد الإلكتروني المتعددة للشركة
    Route::apiResource('mail-settings', MailSettingController::class)->names('mail-settings');
    Route::post('mail-settings/{id}/test', [MailSettingController::class, 'testConnection'])->name('mail-settings.test');
    Route::post('mail-settings/{id}/set-default', [MailSettingController::class, 'setDefault'])->name('mail-settings.set-default');

    // مسارات إدارة حسابات الواتساب المتعددة للشركة
    Route::apiResource('whatsapp-settings', WhatsAppSettingController::class)->names('whatsapp-settings');
    Route::post('whatsapp-settings/{id}/test', [WhatsAppSettingController::class, 'testConnection'])->name('whatsapp-settings.test');
    Route::post('whatsapp-settings/{id}/set-default', [WhatsAppSettingController::class, 'setDefault'])->name('whatsapp-settings.set-default');

    // مسارات أتمتة وجدولة الإشعارات وقوالب الرسائل للشركة
    Route::get('notification-workflows/integrations-status', [NotificationWorkflowController::class, 'integrationsStatus'])->name('notification-workflows.integrations-status');
    Route::apiResource('notification-templates', NotificationTemplateController::class)->names('notification-templates');
    Route::apiResource('notification-workflows', NotificationWorkflowController::class)->names('notification-workflows');
    Route::post('notification-workflows/{id}/run', [NotificationWorkflowController::class, 'runNow'])->name('notification-workflows.run');

    Route::apiResource('notifications', NotificationController::class)->names('notification');
});

