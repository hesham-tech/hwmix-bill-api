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
        // Enforce Arabic locale for all requests
        app()->setLocale(config('app.locale', 'ar'));

        // Register Observers (Most are registered via #[ObservedBy] attribute in Models)
        Role::observe(RoleObserver::class);

        // Super Admin bypass (Global access if user has 'admin.super' permission)
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            static $isSuperAdmin = [];

            if (!isset($isSuperAdmin[$user->id])) {
                // We check if the user has 'admin.super' permission globally (ignoring teams/companies)
                // This is safer and more performance-efficient than manual DB queries
                try {
                    // Temporarily unset team id to check global permission if teams are used
                    $originalTeamId = getPermissionsTeamId();
                    setPermissionsTeamId(null);

                    $isSuperAdmin[$user->id] = $user->hasPermissionTo(perm_key('admin.super'));

                    setPermissionsTeamId($originalTeamId);
                } catch (\Throwable $e) {
                    $isSuperAdmin[$user->id] = false;
                }
            }

            if ($isSuperAdmin[$user->id]) {
                return true;
            }
        });
    }
}
