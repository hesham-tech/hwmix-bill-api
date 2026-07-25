<?php
// مزود خدمات موديول كاش هونكس HwnixCash وتسجيل الحاويات والدورة الحياتية.

namespace Modules\HwnixCash\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\HwnixCash\Domain\Contracts\HwnixCashDeviceRepositoryInterface;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageRepositoryInterface;
use Modules\HwnixCash\Domain\Contracts\HwnixCashWalletTransactionRepositoryInterface;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageSourceRepositoryInterface;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageParserInterface;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashDeviceRepository;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashMessageRepository;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashWalletTransactionRepository;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashMessageSourceRepository;
use Modules\HwnixCash\Services\HwnixCashMessageParserService;
use Modules\HwnixCash\Drivers\HwnixCashDriverManager;

class HwnixCashServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'HwnixCash';

    protected string $nameLower = 'hwnixcash';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        // ربط الواجهات بالمستودعات الكائنية
        $this->app->bind(HwnixCashDeviceRepositoryInterface::class, EloquentHwnixCashDeviceRepository::class);
        $this->app->bind(HwnixCashMessageRepositoryInterface::class, EloquentHwnixCashMessageRepository::class);
        $this->app->bind(HwnixCashWalletTransactionRepositoryInterface::class, EloquentHwnixCashWalletTransactionRepository::class);
        $this->app->bind(HwnixCashMessageSourceRepositoryInterface::class, EloquentHwnixCashMessageSourceRepository::class);

        // ربط نقطة التوسع المعمارية النظيفة للـ Stub Parser
        $this->app->bind(HwnixCashMessageParserInterface::class, HwnixCashMessageParserService::class);

        // تسجيل مدير السائقين كـ Singleton
        $this->app->singleton(HwnixCashDriverManager::class, function ($app) {
            return new HwnixCashDriverManager($app);
        });
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->name, 'config/config.php') => config_path($this->nameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->name, 'config/config.php'),
            $this->nameLower
        );
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->nameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->nameLower)) {
                $paths[] = $path . '/modules/' . $this->nameLower;
            }
        }
        return $paths;
    }
}
