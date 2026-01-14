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
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        \App\Models\InvoicePayment::observe(\App\Observers\PaymentObserver::class);
        // Company Observer registered via #[ObservedBy] attribute in Company model
        Role::observe(RoleObserver::class);

        // Super Admin bypass (Global access if user has 'admin.super' permission in any company context)
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            static $superAdmins = [];

            if (!isset($superAdmins[$user->id])) {
                $superAdmins[$user->id] = \Illuminate\Support\Facades\DB::table('model_has_permissions')
                    ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('model_id', $user->id)
                    ->where('model_type', get_class($user))
                    ->where('permissions.name', perm_key('admin.super'))
                    ->exists();
            }

            if ($superAdmins[$user->id]) {
                return true;
            }
        });
    }
}
