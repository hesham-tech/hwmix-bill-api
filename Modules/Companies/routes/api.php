<?php

use Illuminate\Support\Facades\Route;
use Modules\Companies\Http\Controllers\BranchController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Branch Routes
    Route::post('branches/{branch}/assign-users', [BranchController::class, 'assignUsers']);
    Route::post('branches/{branch}/remove-users', [BranchController::class, 'removeUsers']);
    Route::apiResource('branches', BranchController::class);
});
