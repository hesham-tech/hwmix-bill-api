<?php

use Illuminate\Support\Facades\Route;
use Modules\SmsGateway\Http\Controllers\SmsGatewayController;
use Modules\SmsGateway\Http\Controllers\Api\v1\AgentDeviceController;

Route::get('download-app', [AgentDeviceController::class, 'showDownloadsPage']);
Route::get('download-app/latest', [AgentDeviceController::class, 'downloadLatestApp']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('smsgateways', SmsGatewayController::class)->names('smsgateway');
});

