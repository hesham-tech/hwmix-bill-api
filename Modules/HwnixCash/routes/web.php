<?php
// مسارات الـ Web الخاصة بموديول كاش هونكس HwnixCash.

use Illuminate\Support\Facades\Route;
use Modules\HwnixCash\Http\Controllers\Api\v1\AgentDeviceController;

Route::get('hwnix-cash/download-app', [AgentDeviceController::class, 'showDownloadsPage'])->name('hwnix-cash.downloads.app');
