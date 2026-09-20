<?php

namespace Modules\Store\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * مسار الـ API الموديول
     *
     * @var string
     */
    protected $moduleNamespace = 'Modules\Store\Http\Controllers';

    /**
     * تشغيل الخدمات
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * تعريف المسارات
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();
    }

    /**
     * تعريف مسارات الـ API
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Store', '/routes/api.php'));
    }
}
