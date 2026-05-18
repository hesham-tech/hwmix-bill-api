<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ErrorReportController;

Route::get('/', function () {
    return view('welcome');
});

// For frontend backward compatibility where it calls /api/error-reports instead of /api/v1/error-reports
Route::post('api/error-reports', [ErrorReportController::class, 'store']);
