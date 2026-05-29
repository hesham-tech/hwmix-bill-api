<?php

use Illuminate\Support\Facades\Route;
use Modules\ExportImport\Http\Controllers\ExportImportController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // مسارات محرك التصدير والاستيراد
    Route::get('export-import', [ExportImportController::class, 'index'])->name('export-import.index');
    Route::get('export-import/download/{id}', [ExportImportController::class, 'download'])->name('export-import.download')->withoutMiddleware(['auth:sanctum']); // السماح بالتحميل المباشر بعد التحقق الأمني بالكنترولر
    Route::get('export-import/{id}', [ExportImportController::class, 'show'])->name('export-import.show');
    Route::post('export-import/export', [ExportImportController::class, 'export'])->name('export-import.export');
    Route::post('export-import/import', [ExportImportController::class, 'import'])->name('export-import.import');
});

