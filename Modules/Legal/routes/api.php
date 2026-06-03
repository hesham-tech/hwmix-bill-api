<?php

use Illuminate\Support\Facades\Route;
use Modules\Legal\Http\Controllers\LegalDocumentController;
use Modules\Legal\Http\Controllers\LegalVersionController;
use Modules\Legal\Http\Controllers\LegalAcceptanceController;

/**
 * مسارات الـ API الخاصة بموديول المستندات القانونية.
 */
Route::prefix('v1')->group(function () {
    // ==================== Guest / Public Routes ====================
    Route::get('legal/documents/active-list', [LegalAcceptanceController::class, 'getActiveDocumentsList']);
    Route::get('legal/documents/{key}/active', [LegalAcceptanceController::class, 'getActiveDocumentByKey']);

    // ==================== Authenticated User Routes ====================
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('legal/acceptances/pending', [LegalAcceptanceController::class, 'checkPending']);
        Route::post('legal/acceptances', [LegalAcceptanceController::class, 'accept']);
        Route::get('legal/my-acceptances', [LegalAcceptanceController::class, 'myHistory']);

        // ==================== Admin / Management Routes ====================
        Route::apiResource('legal/admin/documents', LegalDocumentController::class)->names([
            'index' => 'legal.admin.documents.index',
            'store' => 'legal.admin.documents.store',
            'show' => 'legal.admin.documents.show',
            'update' => 'legal.admin.documents.update',
            'destroy' => 'legal.admin.documents.destroy',
        ]);
        
        Route::post('legal/admin/documents/{documentId}/versions', [LegalVersionController::class, 'store']);
        Route::put('legal/admin/versions/{id}', [LegalVersionController::class, 'update']);
        Route::post('legal/admin/versions/{id}/publish', [LegalVersionController::class, 'publish']);
        Route::delete('legal/admin/versions/{id}', [LegalVersionController::class, 'destroy']);
        Route::get('legal/admin/versions/{versionId}/report', [LegalAcceptanceController::class, 'report']);
    });
});
