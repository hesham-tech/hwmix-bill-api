<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Modules\ExportImport\Http\Resources\ExportImportJobResource;
use Modules\ExportImport\Models\ExportImportJob;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Real routes, middleware, Eloquent scopes and local storage; no app boot, .env or providers. */
class ExportDownloadAuthorizationTest extends TestCase
{
    private Application $app;
    private Capsule $db;
    private Router $router;
    private string $directory;
    private ?User $user = null;
    private array $permissions = ['products.export', 'admin.company'];
    private ?int $permissionCompany = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/hwnix-export-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->app = new Application($this->directory);
        $this->app->instance('config', new Repository([
            'app' => ['active_branch_id' => null],
            'auth' => ['defaults' => ['guard' => 'sanctum'], 'guards' => ['sanctum' => ['driver' => 'fixture']]],
            'filesystems' => ['disks' => ['public' => ['driver' => 'local', 'root' => $this->directory, 'throw' => true]]],
            'permissions_keys' => require dirname(__DIR__, 2).'/config/permissions_keys.php',
        ]));
        $events = new Dispatcher($this->app);
        $this->app->instance('events', $events);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $registrar = \Mockery::mock(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->shouldReceive('setPermissionsTeamId')->andReturnUsing(function ($id) { $this->permissionCompany = $id; });
        $this->app->instance(\Spatie\Permission\PermissionRegistrar::class, $registrar);
        $this->db = new Capsule($this->app);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setEventDispatcher($events);
        $this->db->bootEloquent();
        $this->app->instance('db', $this->db->getDatabaseManager());
        Model::clearBootedModels();
        $connection = $this->db->getConnection();
        $connection->statement('CREATE TABLE export_import_jobs (id INTEGER PRIMARY KEY, company_id INTEGER, branch_id INTEGER, type TEXT, model_type TEXT, status TEXT, file_path TEXT, deleted_at TEXT)');
        $connection->statement('CREATE TABLE company_user (user_id INTEGER, company_id INTEGER, status TEXT)');
        $connection->statement('CREATE TABLE branches (id INTEGER, company_id INTEGER)');
        $connection->table('company_user')->insert(['user_id' => 7, 'company_id' => 1, 'status' => 'active']);
        $connection->table('branches')->insert(['id' => 10, 'company_id' => 1]);
        $connection->table('export_import_jobs')->insert([
            ['id' => 1, 'company_id' => 1, 'branch_id' => 10, 'type' => 'export', 'model_type' => 'products', 'status' => 'completed', 'file_path' => 'fixture.csv', 'deleted_at' => null],
            ['id' => 2, 'company_id' => 2, 'branch_id' => 20, 'type' => 'export', 'model_type' => 'products', 'status' => 'completed', 'file_path' => 'foreign.csv', 'deleted_at' => null],
        ]);
        file_put_contents($this->directory.'/fixture.csv', "ID,name\n1,fixture");
        $this->app->instance('filesystem', new FilesystemManager($this->app));
        $auth = new AuthManager($this->app);
        $auth->viaRequest('fixture', fn (Request $request) => $request->bearerToken() === 'fixture-user' ? $this->user : null);
        $this->app->instance('auth', $auth);
        $this->app->bind(\Illuminate\Contracts\Auth\Factory::class, fn () => $auth);
        $this->router = new Router($events, $this->app);
        $this->app->instance('router', $this->router);
        $this->router->aliasMiddleware('auth', \Illuminate\Auth\Middleware\Authenticate::class);
        $this->router->group(['prefix' => 'api', 'as' => 'api.'], dirname(__DIR__, 2).'/Modules/ExportImport/routes/api.php');
        $this->router->getRoutes()->refreshNameLookups();
        $request = Request::create('https://fixture.invalid');
        $this->app->instance('request', $request);
        $this->app->instance('url', new UrlGenerator($this->router->getRoutes(), $request));
        $this->user = \Mockery::mock(User::class)->makePartial();
        $this->user->setRawAttributes(['id' => 7, 'active_company_id' => 1, 'branch_id' => 10]);
        $this->user->shouldReceive('hasPermissionTo', 'checkPermissionTo')->andReturnUsing(fn ($permission) => in_array($permission, $this->permissions, true));
        $this->user->shouldReceive('getAllowedBranchIds')->andReturn([10]);
    }

    private function request(bool $authenticated = true, int $id = 1, array $headers = []): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('/api/v1/export-import/download/'.$id);
        $request->headers->set('Accept', 'application/json');
        if ($authenticated) $request->headers->set('Authorization', 'Bearer fixture-user');
        foreach ($headers as $name => $value) $request->headers->set($name, $value);
        $this->app->instance('request', $request);
        $this->app['auth']->forgetGuards();
        try {
            return $this->router->dispatch($request);
        } catch (AuthenticationException $e) {
            return new \Illuminate\Http\JsonResponse(['message' => 'Unauthenticated'], 401);
        } catch (HttpExceptionInterface $e) {
            return new \Illuminate\Http\JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    private function update(array $attributes): void
    {
        $this->db->getConnection()->table('export_import_jobs')->where('id', 1)->update($attributes);
    }

    public function test_guest_is_rejected_by_the_actual_route_before_storage(): void
    {
        $this->app->instance('filesystem', new \stdClass());
        $this->assertSame(401, $this->request(false)->getStatusCode());
    }

    public function test_foreign_company_is_not_found_even_with_a_spoofed_company_header(): void
    {
        $this->assertSame(404, $this->request(true, 2, ['X-HWNIX-COMPANY' => '2', 'X-Company-Id' => '2'])->getStatusCode());
    }

    public function test_permission_is_required_before_storage(): void
    {
        $this->permissions = ['admin.company'];
        $this->app->instance('filesystem', new \stdClass());
        $this->assertSame(403, $this->request()->getStatusCode());
    }

    public function test_authorized_user_receives_only_the_expected_file(): void
    {
        $response = $this->request();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $this->permissionCompany);
        $this->assertStringContainsString('products_export_1.csv', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        ob_start();
        $response->sendContent();
        $this->assertSame("ID,name\n1,fixture", ob_get_clean());
    }

    public function test_inactive_membership_and_missing_company_fail_closed(): void
    {
        $this->db->getConnection()->table('company_user')->update(['status' => 'inactive']);
        $this->assertSame(404, $this->request()->getStatusCode());
        $this->user->active_company_id = null;
        $this->assertSame(404, $this->request()->getStatusCode());
    }

    public function test_unfinished_missing_deleted_import_and_unsupported_jobs_are_denied(): void
    {
        foreach (['pending', 'processing', 'failed'] as $status) {
            $this->update(['status' => $status]);
            $this->assertSame(404, $this->request()->getStatusCode());
        }
        $this->update(['status' => 'completed', 'file_path' => 'missing.csv']);
        $response = $this->request();
        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString($this->directory, $response->getContent());
        $this->update(['file_path' => null]);
        $this->assertSame(404, $this->request()->getStatusCode());
        $this->update(['file_path' => 'fixture.csv', 'deleted_at' => '2026-01-01']);
        $this->assertSame(404, $this->request()->getStatusCode());
        $this->update(['deleted_at' => null, 'type' => 'import']);
        $this->assertSame(404, $this->request()->getStatusCode());
        $this->update(['type' => 'export', 'model_type' => 'invoices']);
        $this->assertSame(403, $this->request()->getStatusCode());
    }

    public function test_branch_scope_is_retained_and_super_admin_stays_in_selected_company(): void
    {
        $this->permissions = ['products.export'];
        $this->update(['branch_id' => 11]);
        $this->assertSame(404, $this->request()->getStatusCode());
        $this->permissions = ['admin.super'];
        $this->assertSame(404, $this->request(true, 2)->getStatusCode());
        $this->update(['branch_id' => 10]);
        $this->assertSame(200, $this->request()->getStatusCode());
    }

    public function test_resource_returns_the_actual_named_authenticated_route(): void
    {
        $job = new ExportImportJob(['id' => 1, 'type' => 'export', 'model_type' => 'products', 'status' => 'completed', 'file_path' => 'fixture.csv']);
        $resource = (new ExportImportJobResource($job))->toArray($this->app['request']);
        $this->assertSame('https://fixture.invalid/api/v1/export-import/download/1', $resource['download_url']);
        $route = $this->router->getRoutes()->getByName('api.export-import.download');
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    protected function tearDown(): void
    {
        $this->db->getDatabaseManager()->disconnect();
        Model::unsetConnectionResolver();
        Model::unsetEventDispatcher();
        Model::clearBootedModels();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        \Mockery::close();
        unlink($this->directory.'/fixture.csv');
        rmdir($this->directory);
        parent::tearDown();
    }
}
