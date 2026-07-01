<?php

use Illuminate\Support\Facades\Route;
use Modules\SmsGateway\Http\Controllers\SmsGatewayController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('smsgateways', SmsGatewayController::class)->names('smsgateway');
});
