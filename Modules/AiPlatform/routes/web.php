<?php

// مسارات Dashboard
use Illuminate\Support\Facades\Route;

if (config('ai-platform.dashboard.enabled', true)) {
    Route::prefix(config('ai-platform.dashboard.prefix', 'ai-platform'))
        ->middleware(config('ai-platform.dashboard.middleware', ['web', 'auth']))
        ->name('ai-platform.')
        ->group(function () {
            Route::get('/', fn() => view('ai-platform::dashboard'))->name('dashboard');
            Route::get('/providers', fn() => view('ai-platform::providers.index'))->name('providers.index');
            Route::get('/agents', fn() => view('ai-platform::agents.index'))->name('agents.index');
            Route::get('/prompts', fn() => view('ai-platform::prompts.index'))->name('prompts.index');
            Route::get('/usage', fn() => view('ai-platform::usage.index'))->name('usage.index');
        });
}
