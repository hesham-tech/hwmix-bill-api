<?php

use Illuminate\Support\Facades\Route;
use Modules\ExportImport\Http\Controllers\ExportImportController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('exportimports', ExportImportController::class)->names('exportimport');
});
