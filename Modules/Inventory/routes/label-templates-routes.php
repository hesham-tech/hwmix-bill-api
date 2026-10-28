<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\LabelPrintDataController;
use Modules\Inventory\Http\Controllers\LabelTemplateController;

Route::middleware(['auth:sanctum', 'scope_company', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('label-templates/fields', [LabelTemplateController::class, 'fields']);
    Route::get('label-templates/products', [LabelTemplateController::class, 'products']);
    Route::post('label-templates/assets', [LabelTemplateController::class, 'uploadAsset']);
    Route::get('label-templates/assets/{id}', [LabelTemplateController::class, 'asset'])->whereNumber('id');
    Route::apiResource('label-templates', LabelTemplateController::class)->parameters(['label-templates' => 'id'])->where(['id' => '[0-9]+']);
    Route::patch('label-templates/{id}/metadata', [LabelTemplateController::class, 'metadata'])->whereNumber('id');
    Route::post('label-templates/{id}/duplicate', [LabelTemplateController::class, 'duplicate'])->whereNumber('id');
    Route::post('label-templates/{id}/publish', [LabelTemplateController::class, 'publish'])->whereNumber('id');
    Route::post('label-templates/{id}/restore', [LabelTemplateController::class, 'restore'])->whereNumber('id');
    Route::patch('label-templates/{id}/default', [LabelTemplateController::class, 'setDefault'])->whereNumber('id');
    Route::post('label-print-data', LabelPrintDataController::class);
    Route::post('label-template-preview-data', [LabelPrintDataController::class, 'preview']);
});
