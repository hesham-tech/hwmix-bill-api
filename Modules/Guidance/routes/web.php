<?php

use Illuminate\Support\Facades\Route;
use Modules\Guidance\Http\Controllers\GuidanceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('guidances', GuidanceController::class)->names('guidance');
});
