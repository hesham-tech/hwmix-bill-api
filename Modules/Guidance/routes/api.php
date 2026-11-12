<?php

use Illuminate\Support\Facades\Route;
use Modules\Guidance\Http\Controllers\GuidanceController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('guidance')->group(function () {
        Route::get('setup', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'show']);
        Route::put('setup/steps/{step}', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'save']);
        Route::patch('setup/preferences', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'preferences']);
        Route::get('progress', [GuidanceController::class, 'index'])->name('guidance.progress');
        Route::post('complete', [GuidanceController::class, 'complete'])->name('guidance.complete');
        Route::post('uncomplete', [GuidanceController::class, 'uncomplete'])->name('guidance.uncomplete');
        Route::post('reset', [GuidanceController::class, 'reset'])->name('guidance.reset');
    });
});

