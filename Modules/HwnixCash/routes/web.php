<?php
// مسارات الـ Web الخاصة بموديول كاش هونكس HwnixCash وتنزيل التطبيق.

use Illuminate\Support\Facades\Route;
use Modules\HwnixCash\Http\Controllers\Api\v1\AgentDeviceController;

Route::get('download-app', [AgentDeviceController::class, 'showDownloadsPage'])->name('hwnix-cash.downloads.page');
Route::get('download-app/latest', [AgentDeviceController::class, 'downloadLatestApp'])->name('hwnix-cash.downloads.app');
