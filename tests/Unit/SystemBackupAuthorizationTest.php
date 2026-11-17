<?php
namespace Tests\Unit;

use App\Models\{Backup, BackupSetting, User};
use App\Services\{BackupConfirmation, BackupService};
use Illuminate\Auth\{AuthManager, AuthenticationException};
use Illuminate\Cache\{ArrayStore, RateLimiter, Repository as CacheRepository};
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Hashing\HashManager;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** No application boot, .env, actual archive command or restore. All DB/storage are explicit fixtures. */
class SystemBackupAuthorizationTest extends TestCase
{
    private Application $app;
    private Capsule $db;
    private Router $router;
    private User $user;
    private string $directory;
    private array $permissions = ['admin.super'];
    private $service;
    private $logger;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/hwnix-backup-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/system', 0700, true);
        file_put_contents($this->directory.'/system/fixture.zip', 'synthetic-archive');
        $this->app = new Application($this->directory);
        $this->app->instance('config', new Repository([
            'app' => ['debug' => false, 'active_branch_id' => null],
            'auth' => ['defaults' => ['guard' => 'sanctum'], 'guards' => ['sanctum' => ['driver' => 'fixture']]],
            'hashing' => ['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 4, 'verify' => true]],
            'filesystems' => ['disks' => ['local' => ['driver' => 'local', 'root' => $this->directory, 'throw' => true]]],
            'backup' => ['backup' => ['name' => 'system']],
            'permissions_keys' => require dirname(__DIR__, 2).'/config/permissions_keys.php',
        ]));
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->app);
        $events = new Dispatcher($this->app); $this->app->instance('events', $events);
        $this->db = new Capsule($this->app);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setEventDispatcher($events); $this->db->bootEloquent();
        $this->app->instance('db', $this->db->getDatabaseManager()); Model::clearBootedModels();
        $connection = $this->db->getConnection();
        $connection->statement('CREATE TABLE backup_history (id INTEGER PRIMARY KEY, filename TEXT, disk TEXT, status TEXT, type TEXT, size_bytes INTEGER, error_message TEXT, completed_at TEXT, created_at TEXT, updated_at TEXT)');
        $connection->statement('CREATE TABLE backup_settings (id INTEGER PRIMARY KEY, key TEXT UNIQUE, value TEXT, type TEXT, created_at TEXT, updated_at TEXT)');
        $connection->table('backup_history')->insert(['id' => 1, 'filename' => 'fixture.zip', 'disk' => 'local', 'status' => 'success', 'type' => 'manual', 'size_bytes' => 17, 'error_message' => 'fixture-private-error', 'created_at' => '2026-01-01']);
        $connection->table('backup_settings')->insert(['key' => 'backup_restore_token', 'value' => 'fixture-secondary-secret', 'type' => 'string']);
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
        $this->app->instance('validator', new \Illuminate\Validation\Factory($translator, $this->app));
        $responses = \Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $responses->shouldReceive('json')->andReturnUsing(fn ($data = [], $status = 200, $headers = [], $options = 0) => new JsonResponse($data, $status, $headers, $options));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $responses);
        $this->logger = new class extends \Psr\Log\AbstractLogger {
            public array $records = [];
            public function log($level, \Stringable|string $message, array $context = []): void { $this->records[] = [$message, $context]; }
        };
        $this->app->instance('log', $this->logger);
        $this->app->instance('filesystem', new FilesystemManager($this->app));
        $this->app->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
        $this->app->instance('hash', new HashManager($this->app));
        $limiter = new RateLimiter(new CacheRepository(new ArrayStore()));
        $limiter->for('api', fn () => \Illuminate\Cache\RateLimiting\Limit::none());
        $this->app->instance(RateLimiter::class, $limiter);
        $registrar = \Mockery::mock(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->shouldReceive('setPermissionsTeamId');
        $this->app->instance(\Spatie\Permission\PermissionRegistrar::class, $registrar);
        $this->service = \Mockery::mock(BackupService::class);
        $this->app->instance(BackupService::class, $this->service);
        $this->user = \Mockery::mock(User::class)->makePartial();
        $this->user->setRawAttributes(['id' => 7, 'active_company_id' => 1, 'branch_id' => null,
            'password' => password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4])]);
        $this->user->shouldReceive('checkPermissionTo', 'hasPermissionTo')->andReturnUsing(fn ($permission) => in_array($permission, $this->permissions, true));
        $this->user->shouldReceive('hasCapability')->andReturn(false);
        $this->user->shouldReceive('getBalanceAttribute')->andReturn(0);
        $auth = new AuthManager($this->app);
        $auth->viaRequest('fixture', fn (Request $request) => in_array($request->bearerToken(), ['fixture-A', 'fixture-B'], true) ? $this->user : null);
        $this->app->instance('auth', $auth); $this->app->bind(\Illuminate\Contracts\Auth\Factory::class, fn () => $auth);
        $this->router = new Router($events, $this->app); $this->app->instance('router', $this->router);
        $this->router->aliasMiddleware('auth', \Illuminate\Auth\Middleware\Authenticate::class);
        $this->router->aliasMiddleware('scope_company', \App\Http\Middleware\ScopePermissionsByCompany::class);
        $this->router->aliasMiddleware('branch_context', \App\Http\Middleware\BranchContextMiddleware::class);
        $this->router->aliasMiddleware('throttle', \Illuminate\Routing\Middleware\ThrottleRequests::class);
        // Register actual application routes; only backup routes are dispatched, and no providers boot.
        $this->router->group(['prefix' => 'api/v1'], dirname(__DIR__, 2).'/routes/api.php');
    }

    private function call(string $method, string $path, array $data = [], array $headers = [], ?string $identity = 'fixture-A')
    {
        $request = Request::create('/api/v1/backups'.$path, $method, $data);
        $request->headers->set('Accept', 'application/json');
        if ($identity) $request->headers->set('Authorization', 'Bearer '.$identity);
        foreach ($headers as $key => $value) $request->headers->set($key, $value);
        $request->setUserResolver(fn () => $this->app['auth']->user());
        $this->app->instance('request', $request); $this->app['auth']->forgetGuards();
        try {
            return Backup::withoutEvents(fn () => BackupSetting::withoutEvents(fn () => $this->router->dispatch($request)));
        } catch (AuthenticationException) { return new JsonResponse(['message' => 'Unauthenticated'], 401);
        } catch (HttpExceptionInterface $e) { return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode()); }
    }

    private function confirmation(string $action = 'download', int $id = 1): string
    {
        $response = $this->call('POST', '/confirm-password', ['password' => 'fixture-password', 'action' => $action, 'backup_id' => $id]);
        $this->assertSame(200, $response->getStatusCode());
        return json_decode($response->getContent(), true)['data']['confirmation_token'];
    }

    public function test_guest_staff_and_company_manager_are_denied_every_action_before_db_or_storage(): void
    {
        $this->app->instance('filesystem', new \stdClass());
        $connection = $this->db->getConnection();
        foreach ([[null, [], 401], ['fixture-A', [], 403], ['fixture-A', ['admin.company', 'backups.view_all'], 403]] as [$identity, $permissions, $status]) {
            $this->permissions = $permissions;
            $connection->flushQueryLog(); $connection->enableQueryLog();
            foreach ([['GET', ''], ['POST', '/run'], ['GET', '/1/download'], ['DELETE', '/1'], ['GET', '/settings'], ['PUT', '/settings'], ['POST', '/1/restore'], ['POST', '/confirm-password']] as [$method, $path]) {
                $this->assertSame($status, $this->call($method, $path, [], [], $identity)->getStatusCode(), $method.' '.$path);
            }
            $this->assertSame([], $connection->getQueryLog());
        }
    }

    public function test_super_admin_reads_system_history_without_active_company_and_secrets_are_omitted(): void
    {
        $this->user->active_company_id = null;
        $response = $this->call('GET', ''); $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('fixture-private-error', $response->getContent());
        $this->assertStringNotContainsString('"disk"', $response->getContent());
        $settings = $this->call('GET', '/settings'); $this->assertSame(200, $settings->getStatusCode());
        $this->assertCount(4, json_decode($settings->getContent(), true)['data']);
        $this->assertStringNotContainsString('backup_restore_token', $settings->getContent());
        $this->assertStringNotContainsString('fixture-secondary-secret', $settings->getContent());
    }

    public function test_unknown_or_invalid_settings_are_rejected_atomically_and_valid_values_are_typed(): void
    {
        $this->assertSame(422, $this->call('PUT', '/settings', ['backup_time' => '10:00', 'backup_restore_token' => 'replacement'])->getStatusCode());
        $this->assertSame(1, $this->db->getConnection()->table('backup_settings')->count());
        foreach ([['backup_time' => '25:90'], ['backup_frequency' => 'hourly'], ['backup_retention_days' => 0], ['backup_retention_days' => 366], ['backup_include_files' => 'false']] as $invalid) {
            $this->assertSame(422, $this->call('PUT', '/settings', $invalid)->getStatusCode());
        }
        $response = $this->call('PUT', '/settings', ['backup_time' => '11:15', 'backup_retention_days' => 14, 'backup_include_files' => false, 'backup_frequency' => 'none']);
        $this->assertSame(200, $response->getStatusCode());
        $values = array_column(json_decode($response->getContent(), true)['data'], 'value', 'key');
        $this->assertSame(14, $values['backup_retention_days']); $this->assertFalse($values['backup_include_files']);
        $this->assertSame('fixture-secondary-secret', $this->db->getConnection()->table('backup_settings')->where('key', 'backup_restore_token')->value('value'));
    }

    public function test_password_confirmation_is_required_and_rate_limited(): void
    {
        $this->assertSame(403, $this->call('GET', '/1/download')->getStatusCode());
        for ($i = 0; $i < 5; $i++) $this->assertSame(422, $this->call('POST', '/confirm-password', ['password' => 'wrong', 'action' => 'download', 'backup_id' => 1])->getStatusCode());
        $this->assertSame(429, $this->call('POST', '/confirm-password', ['password' => 'fixture-password', 'action' => 'download', 'backup_id' => 1])->getStatusCode());
    }

    public function test_confirmation_is_bound_to_user_bearer_action_archive_and_expiry(): void
    {
        $token = $this->confirmation(); $header = [BackupConfirmation::HEADER => $token];
        $this->assertSame(403, $this->call('GET', '/2/download', [], $header)->getStatusCode());
        $this->assertSame(403, $this->call('POST', '/1/restore', ['token' => 'fixture-secondary-secret'], $header)->getStatusCode());
        $this->assertSame(403, $this->call('GET', '/1/download', [], $header, 'fixture-B')->getStatusCode());
        $this->user->id = 8; $this->assertSame(403, $this->call('GET', '/1/download', [], $header)->getStatusCode()); $this->user->id = 7;
        $this->assertSame(403, $this->call('GET', '/1/download', [], [BackupConfirmation::HEADER => $token.'altered'])->getStatusCode());
        $response = $this->call('GET', '/1/download', [], $header); $this->assertSame(200, $response->getStatusCode());
        ob_start(); $response->sendContent(); $this->assertSame('synthetic-archive', ob_get_clean());
        $this->permissions = ['admin.company']; $this->assertSame(403, $this->call('GET', '/1/download', [], $header)->getStatusCode()); $this->permissions = ['admin.super'];
        \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(301));
        $this->assertSame(403, $this->call('GET', '/1/download', [], $header)->getStatusCode());
    }

    public function test_restore_keeps_secondary_secret_and_calls_only_a_fake_service(): void
    {
        $header = [BackupConfirmation::HEADER => $this->confirmation('restore')];
        $this->assertSame(403, $this->call('POST', '/1/restore', ['token' => 'wrong'], $header)->getStatusCode());
        $this->service->shouldReceive('restore')->once()->with('fixture.zip')->andReturn(true);
        $this->assertSame(200, $this->call('POST', '/1/restore', ['token' => 'fixture-secondary-secret'], $header)->getStatusCode());
        $audit = json_encode($this->logger->records);
        $this->assertStringNotContainsString('fixture-secondary-secret', $audit);
        $this->assertStringNotContainsString('fixture-password', $audit);
        $this->assertStringNotContainsString($header[BackupConfirmation::HEADER], $audit);
    }

    public function test_manual_run_omits_service_output_and_errors_and_delete_uses_only_fixture_storage(): void
    {
        $this->service->shouldReceive('runBackup')->once()->with('manual')->andReturn(['success' => true, 'record' => Backup::find(1), 'output' => 'fixture-private-output']);
        $response = $this->call('POST', '/run'); $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('fixture-private-output', $response->getContent());
        $this->assertStringNotContainsString('fixture-private-error', $response->getContent());
        $this->assertSame(200, $this->call('DELETE', '/1')->getStatusCode());
        $this->assertFileDoesNotExist($this->directory.'/system/fixture.zip');
        $this->assertSame(0, $this->db->getConnection()->table('backup_history')->count());
    }

    public function test_missing_archive_and_service_failures_do_not_disclose_paths_or_command_details(): void
    {
        $header = [BackupConfirmation::HEADER => $this->confirmation()];
        unlink($this->directory.'/system/fixture.zip');
        $missing = $this->call('GET', '/1/download', [], $header);
        $this->assertSame(404, $missing->getStatusCode());
        $this->assertStringNotContainsString($this->directory, $missing->getContent());
        $this->service->shouldReceive('runBackup')->once()->andThrow(new \RuntimeException('fixture-private-driver-output'));
        $failed = $this->call('POST', '/run'); $this->assertSame(500, $failed->getStatusCode());
        $this->assertStringNotContainsString('fixture-private-driver-output', $failed->getContent());
        $this->assertStringNotContainsString('fixture-private-driver-output', json_encode($this->logger->records));
    }

    public function test_legacy_boolean_strings_have_the_same_meaning_in_settings_and_backup_execution(): void
    {
        $this->db->getConnection()->table('backup_settings')->insert(['key' => 'backup_include_files', 'value' => 'false', 'type' => 'boolean']);
        $this->assertFalse(BackupSetting::getVal('backup_include_files'));
        $values = array_column(json_decode($this->call('GET', '/settings')->getContent(), true)['data'], 'value', 'key');
        $this->assertFalse($values['backup_include_files']);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
        $this->db->getDatabaseManager()->disconnect(); Model::unsetConnectionResolver(); Model::unsetEventDispatcher(); Model::clearBootedModels();
        Facade::clearResolvedInstances(); Facade::setFacadeApplication(null); \Mockery::close();
        $path = realpath($this->directory);
        if ($path && str_starts_with($path, realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'hwnix-backup-test-')) (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($path);
        parent::tearDown();
    }
}
