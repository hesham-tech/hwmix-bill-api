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
    private array $queued = [];

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
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
        $validator = new \Illuminate\Validation\Factory($translator, $this->app);
        $this->app->instance('validator', $validator);
        Request::macro('validate', function (array $rules) use ($validator) { return $validator->make($this->all(), $rules)->validate(); });
        $responses = \Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $responses->shouldReceive('json')->andReturnUsing(fn ($data = [], $status = 200, $headers = [], $options = 0) => new \Illuminate\Http\JsonResponse($data, $status, $headers, $options));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $responses);
        $this->app->instance('log', new \Psr\Log\NullLogger());
        $bus = \Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $bus->shouldReceive('dispatch')->andReturnUsing(function ($job) { $this->queued[] = $job; });
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $bus);
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
        $connection->statement('CREATE TABLE export_import_jobs (id INTEGER PRIMARY KEY, company_id INTEGER, branch_id INTEGER, type TEXT, model_type TEXT, status TEXT, file_path TEXT, deleted_at TEXT, progress INTEGER, created_by INTEGER, errors TEXT, created_at TEXT, updated_at TEXT)');
        $connection->statement('CREATE TABLE company_user (user_id INTEGER, company_id INTEGER, status TEXT)');
        $connection->statement('CREATE TABLE branches (id INTEGER, company_id INTEGER, deleted_at TEXT)');
        $connection->statement('CREATE TABLE products (id INTEGER, company_id INTEGER, name TEXT, desc TEXT, active INTEGER)');
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
        $this->user->shouldReceive('hasCapability')->andReturn(false);
        $this->user->shouldReceive('getBalanceAttribute')->andReturn(0);
    }

    private function request(bool $authenticated = true, int $id = 1, array $headers = [], bool $show = false): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create($show ? '/api/v1/export-import/'.$id : '/api/v1/export-import/download/'.$id);
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
        $job = new ExportImportJob(['id' => 1, 'branch_id' => 10, 'type' => 'export', 'model_type' => 'products', 'status' => 'completed', 'file_path' => 'fixture.csv']);
        $resource = (new ExportImportJobResource($job))->toArray($this->app['request']);
        $this->assertSame('https://fixture.invalid/api/v1/export-import/download/1', $resource['download_url']);
        $route = $this->router->getRoutes()->getByName('api.export-import.download');
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    private function schedule(array $headers = []): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('/api/v1/export-import/export', 'POST', ['model_type' => 'products']);
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Authorization', 'Bearer fixture-user');
        foreach ($headers as $name => $value) $request->headers->set($name, $value);
        $this->app->instance('request', $request);
        $this->app['auth']->forgetGuards();
        // Only model observers are suppressed: no audit DB, queue worker or unrelated app service runs.
        return ExportImportJob::withoutEvents(fn () => $this->router->dispatch($request));
    }

    public function test_post_worker_follow_up_and_download_keep_the_selected_branch(): void
    {
        $this->permissions = ['products.export'];
        $db = $this->db->getConnection();
        $db->table('branches')->insert([['id' => 11, 'company_id' => 1], ['id' => 20, 'company_id' => 2]]);
        $db->table('products')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => 'ALLOWED-PRODUCT', 'desc' => '', 'active' => 1],
            ['id' => 2, 'company_id' => 1, 'name' => 'SHARED-CATALOG', 'desc' => '', 'active' => 1],
            ['id' => 3, 'company_id' => 2, 'name' => 'OTHER-COMPANY', 'desc' => '', 'active' => 1],
        ]);
        $response = $this->schedule(['X-Branch-Id' => '10']);
        $this->assertSame(202, $response->getStatusCode());
        $id = json_decode($response->getContent(), true)['data']['id'];
        $this->assertSame(10, (int) $db->table('export_import_jobs')->where('id', $id)->value('branch_id'));
        $this->assertCount(1, $this->queued);
        // A later branch switch must not alter the already-captured worker scope.
        config(['app.active_branch_id' => 11]);
        ExportImportJob::withoutEvents(fn () => $this->queued[0]->handle());
        $job = $db->table('export_import_jobs')->where('id', $id)->first();
        $this->assertSame('completed', $job->status);
        $csv = file_get_contents($this->directory.'/'.$job->file_path);
        $this->assertStringContainsString('ALLOWED-PRODUCT', $csv);
        $this->assertStringContainsString('SHARED-CATALOG', $csv);
        $this->assertStringNotContainsString('OTHER-COMPANY', $csv);
        config(['app.active_branch_id' => 10]);
        $status = $this->request(true, $id, [], true);
        $this->assertSame(200, $status->getStatusCode());
        $this->assertSame(10, (int) json_decode($status->getContent(), true)['data']['branch_id']);
        $download = $this->request(true, $id);
        $this->assertSame(200, $download->getStatusCode());
        ob_start(); $download->sendContent(); $this->assertSame($csv, ob_get_clean());
        config(['app.active_branch_id' => 11]);
        $this->assertSame(404, $this->request(true, $id)->getStatusCode());
        $this->permissions = ['admin.company', 'products.export'];
        $second = $this->schedule(['X-Branch-Id' => '11']);
        $this->assertSame(202, $second->getStatusCode());
        $secondId = json_decode($second->getContent(), true)['data']['id'];
        ExportImportJob::withoutEvents(fn () => $this->queued[1]->handle());
        $secondPath = $db->table('export_import_jobs')->where('id', $secondId)->value('file_path');
        $this->assertNotSame($job->file_path, $secondPath);
        $this->assertStringContainsString('SHARED-CATALOG', file_get_contents($this->directory.'/'.$secondPath));
        $this->assertSame($csv, file_get_contents($this->directory.'/'.$job->file_path));
    }

    public function test_bad_branch_context_never_schedules_a_company_wide_job(): void
    {
        $db = $this->db->getConnection();
        $db->table('branches')->insert([['id' => 11, 'company_id' => 1], ['id' => 20, 'company_id' => 2]]);
        $this->permissions = ['products.export'];
        foreach (['all' => 422, 'not-an-id' => 422, '0' => 422, '11' => 403, '20' => 403] as $branch => $status) {
            $this->assertSame($status, $this->schedule(['X-Branch-Id' => (string) $branch])->getStatusCode());
        }
        $this->assertCount(0, $this->queued);
        $this->assertSame(2, $db->table('export_import_jobs')->count());
        $this->permissions = ['admin.company', 'products.export'];
        $this->user->branch_id = null;
        $this->assertSame(422, $this->schedule()->getStatusCode());
        $this->assertSame(202, $this->schedule(['X-Branch-Id' => '11'])->getStatusCode());
    }

    public function test_legacy_branchless_jobs_are_not_downloadable_or_generated(): void
    {
        $this->update(['branch_id' => null]);
        $this->assertSame(404, $this->request()->getStatusCode());
        $job = new ExportImportJob(['id' => 1, 'status' => 'completed', 'file_path' => 'fixture.csv', 'branch_id' => null]);
        $this->assertNull((new ExportImportJobResource($job))->toArray($this->app['request'])['download_url']);
        ExportImportJob::withoutEvents(fn () => (new \Modules\ExportImport\Jobs\QueuedExportJob(1))->handle());
        $this->assertSame('failed', $this->db->getConnection()->table('export_import_jobs')->where('id', 1)->value('status'));
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
        Request::flushMacros();
        $resolved = realpath($this->directory);
        if ($resolved && str_starts_with($resolved, realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'hwnix-export-test-')) {
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($resolved);
        }
        parent::tearDown();
    }
}
