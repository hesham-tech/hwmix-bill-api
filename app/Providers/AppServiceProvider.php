<?php

namespace App\Providers;


use App\Observers\RoleObserver;
use Spatie\Permission\Models\Role;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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

        // Define Rate Limiters
        $this->configureRateLimiting();

        // Register Observers (Most are registered via #[ObservedBy] attribute in Models)
        Role::observe(RoleObserver::class);

        // Register SaaS Usage Observers to clear cache on resource updates
        \App\Services\SaaS\CachedUsageCounter::registerObservers();

        // Super Admin bypass (Global access if user has 'admin.super' permission)
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            static $isSuperAdmin = [];

            if (!isset($isSuperAdmin[$user->id])) {
                try {
                    $originalTeamId = getPermissionsTeamId();
                    setPermissionsTeamId(null);

                    $isSuperAdmin[$user->id] = $user->hasPermissionTo(perm_key('admin.super'));

                    setPermissionsTeamId($originalTeamId);
                    
                    // تفريغ العلاقات لتجنب تلويث العلاقات المجلوبة بنطاق الشركة النشطة
                    $user->unsetRelation('permissions');
                    $user->unsetRelation('roles');
                } catch (\Throwable $e) {
                    $isSuperAdmin[$user->id] = false;
                }
            }

            if ($isSuperAdmin[$user->id]) {
                return true;
            }
        });

        // تطهير قيمة 'all' لمعرف الفرع قبل الحفظ في قاعدة البيانات لكافة النماذج (Wildcard Listener)
        \Illuminate\Support\Facades\Event::listen([
            'eloquent.saving: *',
            'eloquent.creating: *',
            'eloquent.updating: *'
        ], function ($eventName, array $data) {
            $model = $data[0];
            try {
                if (isset($model->branch_id) && $model->branch_id === 'all') {
                    $model->branch_id = null;
                }
            } catch (\Throwable $e) {
                // تجنب توقف النظام
            }
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            if ($user && $user->hasPermissionTo(perm_key('admin.super'))) {
                return Limit::perMinute(1000)->by($user->id);
            }
            return Limit::perMinute(60)->by($user?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
