<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(function () {
                    Route::post('error-reports', [\App\Http\Controllers\Api\ErrorReportController::class, 'store']);
                });
        },
    )
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\ApiVersionFallbackMiddleware::class);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'scope_company' => \App\Http\Middleware\ScopePermissionsByCompany::class,
            'branch_context' => \App\Http\Middleware\BranchContextMiddleware::class,
            'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
            'staff.only' => \App\Http\Middleware\CheckUserTypeMiddleware::class,
            'saas.limit' => \App\Http\Middleware\CheckSaaSResourceLimit::class,
            'mobile_company_context' => \App\Http\Middleware\MobileCompanyContextMiddleware::class,
            'company.active' => \App\Http\Middleware\EnsureCompanyIsActive::class,
        ]);
        $middleware->appendToGroup('api', \App\Http\Middleware\MobileCompanyContextMiddleware::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\EnsureCompanyIsActive::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\ScopePermissionsByCompany::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\BranchContextMiddleware::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\IdempotencyMiddleware::class);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('store:tick --limit=10 --seconds=20')->everyMinute()->withoutOverlapping(2);
        $schedule->command('app:scheduled-backup')->everyMinute()->withoutOverlapping(120);
        $schedule->command('backup:clean')->dailyAt('04:00')->timezone('UTC')->withoutOverlapping(120);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            return api_error('غير مصرح - يرجى تسجيل الدخول.', [], 401);
        });
    })->create();
