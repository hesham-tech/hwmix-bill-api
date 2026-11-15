<?php

namespace Tests\Unit;

use App\Http\Controllers\RevenueController as LegacyRevenueController;
use App\Services\RevenueService;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Modules\Accounting\Http\Controllers\RevenueController;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class RevenueControllerBootstrapTest extends TestCase
{
    public function test_legacy_controller_loads_and_resolves_without_running_financial_operations(): void
    {
        $container = new Container();
        $service = $this->createMock(RevenueService::class);
        $container->instance(RevenueService::class, $service);

        $controller = $container->make(LegacyRevenueController::class);

        $this->assertSame($service, (new ReflectionProperty($controller, 'revenueService'))->getValue($controller));
        $this->assertSame(
            ['company', 'customer', 'creator'],
            (new ReflectionProperty($controller, 'relations'))->getValue($controller),
        );
    }

    public function test_live_revenue_routes_still_use_the_accounting_controller_and_authentication(): void
    {
        $previousApplication = Facade::getFacadeApplication();
        $container = new Container();
        $router = new Router(new Dispatcher($container), $container);
        $container->instance('router', $router);
        Facade::clearResolvedInstance('router');
        Facade::setFacadeApplication($container);

        try {
            $router->middleware('api')->prefix('api')->group(
                dirname(__DIR__, 2) . '/Modules/Accounting/routes/api.php',
            );

            $routes = array_values(array_filter(
                iterator_to_array($router->getRoutes()),
                fn ($route) => $route->uri() === 'api/v1/revenues'
                    || $route->uri() === 'api/v1/revenues/{revenue}',
            ));

            $this->assertCount(5, $routes);

            foreach ($routes as $route) {
                $this->assertStringStartsWith(RevenueController::class . '@', $route->getActionName());
                $this->assertContains('auth:sanctum', $route->gatherMiddleware());
                $this->assertContains('api', $route->gatherMiddleware());
            }
        } finally {
            Facade::clearResolvedInstance('router');
            Facade::setFacadeApplication($previousApplication);
        }
    }
}
