<?php
// كلاس مزود الخدمة لموديول بوابة الرسائل لإدارة وتسجيل الخدمات.

namespace Modules\SmsGateway\Providers;

use Illuminate\Support\ServiceProvider;

class SmsGatewayServiceProvider extends ServiceProvider
{
    protected string $name = 'SmsGateway';
    protected string $nameLower = 'smsgateway';

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        $this->app->bind(
            \Modules\SmsGateway\Domain\Contracts\SmsDeviceRepositoryInterface::class,
            \Modules\SmsGateway\Repositories\Eloquent\EloquentSmsDeviceRepository::class
        );

        $this->app->bind(
            \Modules\SmsGateway\Domain\Contracts\SmsMessageRepositoryInterface::class,
            \Modules\SmsGateway\Repositories\Eloquent\EloquentSmsMessageRepository::class
        );
        
        $this->app->bind(
            \Modules\SmsGateway\Domain\Contracts\SmsTransportDriverInterface::class,
            \Modules\SmsGateway\Drivers\AndroidAgentDriver::class
        );
    }

    public function boot(): void
    {
        // تحميل الـ Config
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('smsgateway.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php', 'smsgateway'
        );

        // تحميل الـ Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
