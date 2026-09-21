<?php

namespace Modules\Store\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Store\Events\SubOrderStatusChanged;
use Modules\Store\Listeners\CreateInvoiceForDeliveredOrder;

class StoreServiceProvider extends ServiceProvider
{
    /**
     * اسم الموديول
     *
     * @var string
     */
    protected $moduleName = 'Store';

    /**
     * اسم الموديول بالأحرف الصغيرة
     *
     * @var string
     */
    protected $moduleNameLower = 'store';

    /**
     * تشغيل الخدمات
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        // Register Event Listeners for Store Module
        Event::listen(
            SubOrderStatusChanged::class,
            CreateInvoiceForDeliveredOrder::class
        );
    }

    /**
     * تسجيل الخدمات
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * تسجيل إعدادات الموديول
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'config/config.php'), $this->moduleNameLower
        );
    }
}
