<?php

namespace Modules\Inventory\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Attribute;
use Modules\Inventory\Models\AttributeValue;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Inventory';

    /**
     * تسجيل الـ route model bindings لـ Inventory Module
     * يُصلح مشكلة "App\Models\Product not found" عند استخدام Route Model Binding
     */
    public function boot(): void
    {
        // تسجيل صريح لربط الـ Models بالـ routes
        Route::model('product', Product::class);
        Route::model('variant', ProductVariant::class);
        Route::model('brand', Brand::class);
        Route::model('category', Category::class);
        Route::model('warehouse', Warehouse::class);
        Route::model('stock', Stock::class);
        Route::model('attribute', Attribute::class);
        Route::model('attributeValue', AttributeValue::class);

        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
