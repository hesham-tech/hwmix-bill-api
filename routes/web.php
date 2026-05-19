<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ErrorReportController;

Route::get('/', function () {
    return view('welcome');
});

