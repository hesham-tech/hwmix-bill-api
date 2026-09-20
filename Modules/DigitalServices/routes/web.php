<?php

use Illuminate\Support\Facades\Route;
use Modules\DigitalServices\Http\Controllers\DigitalServicesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('digitalservices', DigitalServicesController::class)->names('digitalservices');
});
