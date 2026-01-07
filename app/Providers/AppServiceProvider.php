<?php

namespace App\Providers;


use App\Observers\RoleObserver;
use Spatie\Permission\Models\Role;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Observers
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        \App\Models\InvoicePayment::observe(\App\Observers\PaymentObserver::class);
        // Company Observer registered via #[ObservedBy] attribute in Company model
        Role::observe(RoleObserver::class);
    }
}
