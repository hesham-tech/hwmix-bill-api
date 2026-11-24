<?php
namespace Tests\Unit;

use App\Models\User;
use Illuminate\Auth\{AuthManager, AuthenticationException};
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Modules\AiPlatform\Contracts\Security\SecretVaultInterface;

/** Explicit in-memory DB; no application boot, .env, providers, remote AI, filesystem or persistent data. */
final class AiAdministrationAuthorizationTest extends TestCase
{
    private Application $app;
    private Capsule $db;
    private Router $router;
    private User $user;
    private array $permissions = ['admin.super'];

    protected function setUp(): void
    {
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository([
            'app' => ['debug' => false],
            'auth' => ['defaults' => ['guard' => 'sanctum'], 'guards' => ['sanctum' => ['driver' => 'fixture']]],
            // The inner admin group must remain protected even if outer middleware is configurable.
            'ai-platform' => ['api' => ['prefix' => 'api/v1/ai', 'middleware' => []]],
            'permissions_keys' => require dirname(__DIR__, 2).'/config/permissions_keys.php',
        ]));
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->app);
        $events = new Dispatcher($this->app); $this->app->instance('events', $events);
        $this->db = new Capsule($this->app);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setEventDispatcher($events); $this->db->bootEloquent();
        $this->app->instance('db', $this->db->getDatabaseManager());
        $this->app->instance('db.schema', $this->db->getConnection()->getSchemaBuilder());
        Model::clearBootedModels();
        $connection = $this->db->getConnection();
        $connection->statement('CREATE TABLE companies (id INTEGER PRIMARY KEY, deleted_at TEXT)');
        $connection->table('companies')->insert([['id' => 1], ['id' => 2]]);
        // Actual checked-in migrations, never an invented permissive AI schema.
        $migrations = dirname(__DIR__, 2).'/Modules/AiPlatform/database/migrations/';
        foreach (['000002_create_ai_providers_table', '000003_create_ai_models_table', '000008_create_ai_prompts_table',
            '000009_create_ai_prompt_versions_table', '000011_create_ai_agents_table', '000022_create_ai_provider_accounts_table'] as $file) {
            (require $migrations.'2024_01_01_'.$file.'.php')->up();
        }
        (require $migrations.'2026_07_30_000003_add_account_scope_to_ai_provider_accounts_table.php')->up();
        $connection->statement('CREATE TABLE ai_capabilities (id INTEGER PRIMARY KEY, is_active INTEGER)');
        $connection->statement('CREATE TABLE ai_usage_logs (id INTEGER PRIMARY KEY, company_id INTEGER, total_cost NUMERIC, total_tokens INTEGER, created_at TEXT)');
        $connection->table('ai_providers')->insert(['id' => 1, 'key' => 'fixture', 'label' => 'Fixture', 'type' => 'llm', 'driver_class' => 'never-called', 'is_active' => 1]);
        foreach ([1, 2, null] as $index => $company) {
            $id = $index + 1;
            $connection->table('ai_provider_accounts')->insert(['id' => $id, 'company_id' => $company, 'ai_provider_id' => 1, 'label' => 'Account-'.$id, 'api_key_encrypted' => 'fixture-cipher-'.$id, 'api_key_hint' => 'hint', 'is_active' => 1]);
            $connection->table('ai_agents')->insert(['id' => $id, 'company_id' => $company, 'key' => 'agent-'.$id, 'label' => 'Agent-'.$id, 'is_active' => 1]);
            $connection->table('ai_prompts')->insert(['id' => $id, 'company_id' => $company, 'key' => 'prompt-'.$id, 'label' => 'Prompt-'.$id, 'type' => 'system', 'is_active' => 1]);
        }
        $connection->table('ai_usage_logs')->insert([['id' => 1, 'company_id' => 1, 'total_cost' => 3, 'total_tokens' => 10], ['id' => 2, 'company_id' => 2, 'total_cost' => 99, 'total_tokens' => 99]]);
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
        $factory = new \Illuminate\Validation\Factory($translator, $this->app);
        $factory->setPresenceVerifier(new \Illuminate\Validation\DatabasePresenceVerifier($this->db->getDatabaseManager()));
        $this->app->instance('validator', $factory);
        $responses = \Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $responses->shouldReceive('json')->andReturnUsing(fn ($data = [], $status = 200, $headers = [], $options = 0) => new JsonResponse($data, $status, $headers, $options));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $responses);
        $this->app->resolving(FormRequest::class, function ($form, $app) {
            FormRequest::createFrom($app['request'], $form);
            $form->setContainer($app);
        });
        $this->app->afterResolving(\Illuminate\Contracts\Validation\ValidatesWhenResolved::class, fn ($form) => $form->validateResolved());
        $vault = \Mockery::mock(SecretVaultInterface::class);
        $vault->shouldReceive('encrypt')->andReturnUsing(fn ($value) => 'encrypted-fixture-'.$value);
        $vault->shouldReceive('hint')->andReturn('hint');
        $this->app->instance(SecretVaultInterface::class, $vault);
        $this->user = \Mockery::mock(User::class)->makePartial();
        $this->user->setRawAttributes(['id' => 7, 'active_company_id' => 1]);
        $this->user->shouldReceive('checkPermissionTo')->andReturnUsing(fn ($permission) => in_array($permission, $this->permissions, true));
        $auth = new AuthManager($this->app);
        $auth->viaRequest('fixture', fn (Request $r) => $r->bearerToken() === 'fixture' ? $this->user : null);
        $this->app->instance('auth', $auth); $this->app->bind(\Illuminate\Contracts\Auth\Factory::class, fn () => $auth);
        $this->router = new Router($events, $this->app); $this->app->instance('router', $this->router);
        $this->router->aliasMiddleware('auth', \Illuminate\Auth\Middleware\Authenticate::class);
        require dirname(__DIR__, 2).'/Modules/AiPlatform/routes/api.php';
    }

    private function call(string $method, string $path, array $body = [], bool $authenticated = true)
    {
        $r = Request::create('/api/v1/ai/admin/'.$path, $method, $body);
        $r->headers->set('Accept', 'application/json');
        if ($authenticated) $r->headers->set('Authorization', 'Bearer fixture');
        $r->setUserResolver(fn () => $this->app['auth']->user());
        $this->app->instance('request', $r); $this->app['auth']->forgetGuards();
        try { return $this->router->dispatch($r); }
        catch (AuthenticationException) { return new JsonResponse(['message' => 'unauthenticated'], 401); }
        catch (HttpResponseException $e) { return $e->getResponse(); }
    }

    private function data($response): array { return json_decode($response->getContent(), true)['data']; }

    public function test_every_actual_admin_route_denies_guest_staff_and_manager_before_data_access(): void
    {
        $routes = array_filter($this->router->getRoutes()->getRoutes(), fn ($r) => str_starts_with($r->getName() ?? '', 'ai.admin.'));
        $this->assertCount(23, $routes);
        foreach ([[false, [], 401], [true, [], 403], [true, ['admin.company'], 403]] as [$authenticated, $permissions, $status]) {
            $this->permissions = $permissions;
            $db = $this->db->getConnection(); $db->enableQueryLog(); $db->flushQueryLog();
            foreach ($routes as $route) {
                $path = str_replace(['api/v1/ai/admin/', '{id}'], ['', '3'], $route->uri());
                $response = $this->call($route->methods()[0], $path, ['company_id' => 2, 'is_system' => true, 'label' => 'attacker'], $authenticated);
                $this->assertSame($status, $response->getStatusCode(), $route->getName());
            }
            $this->assertSame([], $db->getQueryLog(), 'Denied actors must not read or mutate AI data');
        }
    }

    public function test_active_company_controls_lists_stats_and_providers_instead_of_default_one(): void
    {
        $this->user->active_company_id = 2;
        foreach (['accounts', 'agents'] as $path) {
            $r = $this->call('GET', $path); $this->assertSame(200, $r->getStatusCode());
            $this->assertSame([2], array_column($this->data($r), 'id'));
        }
        $this->assertSame([2, 3], array_column($this->data($this->call('GET', 'prompts')), 'id'));
        $this->assertSame([2], array_column($this->data($this->call('GET', 'usage-report')), 'id'));
        $stats = $this->data($this->call('GET', 'stats')); $this->assertSame(99.0, (float) $stats['total_usage_cost']);
        $providers = $this->data($this->call('GET', 'providers')); $this->assertSame([2], array_column($providers[0]['accounts'], 'id'));
        $this->assertSame(403, $this->call('GET', 'accounts', ['company_id' => 1])->getStatusCode());
        $this->assertSame(200, $this->call('GET', 'accounts', ['company_id' => '2'])->getStatusCode());
    }

    public function test_scope_input_cannot_change_tenant_or_silently_create_company_one(): void
    {
        foreach ([2, null, 0, -1, 'all', ['1'], '1x'] as $id) {
            $this->assertSame(403, $this->call('POST', 'accounts', ['company_id' => $id])->getStatusCode());
        }
        foreach ([null, 0, 999] as $id) {
            $this->user->active_company_id = $id;
            $this->assertSame(422, $this->call('GET', 'agents')->getStatusCode());
        }
        $this->user->active_company_id = 1;
        $this->db->getConnection()->table('companies')->where('id', 1)->update(['deleted_at' => '2026-01-01']);
        $this->assertSame(422, $this->call('GET', 'accounts')->getStatusCode());
        $this->assertSame(422, $this->call('GET', 'accounts', ['is_system' => 'yes'])->getStatusCode());
    }

    public function test_foreign_or_global_ids_do_not_fall_through_company_mutation_scope(): void
    {
        foreach (['accounts', 'agents', 'prompts'] as $kind) {
            foreach ([2, 3, 999] as $id) {
                foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                    $r = $this->call($method, $kind.'/'.$id.($method === 'PATCH' ? '/toggle' : ''), ['label' => 'no-change']);
                    $this->assertSame(404, $r->getStatusCode(), $method.' '.$kind.'/'.$id);
                }
            }
        }
        $this->assertSame('Account-2', $this->db->getConnection()->table('ai_provider_accounts')->where('id', 2)->value('label'));
        $this->assertSame(1, $this->db->getConnection()->table('ai_prompts')->where('id', 3)->value('is_active'));
    }

    public function test_super_admin_can_manage_shared_models_without_an_active_company(): void
    {
        $this->user->active_company_id = null;
        $r = $this->call('POST', 'models', ['ai_provider_id' => 1, 'model_id' => 'fixture-model', 'label' => 'Fixture']);
        $this->assertSame(201, $r->getStatusCode()); $id = $this->data($r)['id'];
        $this->assertSame(200, $this->call('PUT', 'models/'.$id, ['label' => 'Updated'])->getStatusCode());
        $this->assertSame(200, $this->call('PATCH', 'models/'.$id.'/toggle')->getStatusCode());
        $this->assertSame(200, $this->call('GET', 'models')->getStatusCode());
        $this->assertSame(200, $this->call('DELETE', 'models/'.$id)->getStatusCode());
        $this->assertSame(404, $this->call('DELETE', 'models/'.$id)->getStatusCode());
    }

    public function test_account_create_update_toggle_delete_use_trusted_company_and_explicit_system_scope(): void
    {
        foreach ([false, true] as $system) {
            $r = $this->call('POST', 'accounts', ['ai_provider_id' => 1, 'label' => 'New', 'api_key_encrypted' => 'synthetic', 'is_system' => $system]);
            $this->assertSame(201, $r->getStatusCode()); $id = $this->data($r)['id'];
            $db = $this->db->getConnection();
            $this->assertSame($system ? null : 1, $db->table('ai_provider_accounts')->where('id', $id)->value('company_id'));
            $this->assertSame($system ? 'SYSTEM' : 'COMPANY', $db->table('ai_provider_accounts')->where('id', $id)->value('account_scope'));
            $r = $this->call('PUT', 'accounts/'.$id, ['is_system' => $system, 'label' => 'Updated', 'company_id' => 1, 'account_scope' => 'SHARED']);
            $this->assertSame(200, $r->getStatusCode());
            $this->assertSame($system ? null : 1, $db->table('ai_provider_accounts')->where('id', $id)->value('company_id'));
            $this->assertSame($system ? 'SYSTEM' : 'COMPANY', $db->table('ai_provider_accounts')->where('id', $id)->value('account_scope'));
            $this->assertSame(200, $this->call('PATCH', 'accounts/'.$id.'/toggle', ['is_system' => $system])->getStatusCode());
            $this->assertSame(200, $this->call('DELETE', 'accounts/'.$id, ['is_system' => $system])->getStatusCode());
        }
    }

    public function test_global_prompt_mutations_require_explicit_scope_without_reassigning_ownership(): void
    {
        $this->assertSame(200, $this->call('PUT', 'prompts/3', ['is_system' => true])->getStatusCode());
        $this->assertSame(200, $this->call('PATCH', 'prompts/3/toggle', ['is_system' => true])->getStatusCode());
        $this->assertNull($this->db->getConnection()->table('ai_prompts')->where('id', 3)->value('company_id'));
        $this->assertSame(200, $this->call('DELETE', 'prompts/3', ['is_system' => true])->getStatusCode());
    }

    public function test_form_requests_reject_invalid_types_before_any_mutation(): void
    {
        foreach ([['models', ['ai_provider_id' => 999, 'model_id' => [], 'label' => 'x']],
            ['accounts', ['ai_provider_id' => 1, 'label' => 'x', 'api_key_encrypted' => []]],
            ['agents', ['key' => 'x', 'system_prompt' => []]], ['prompts', ['key' => 'x', 'title' => 'x', 'template' => []]]] as [$path, $body]) {
            $this->assertSame(422, $this->call('POST', $path, $body)->getStatusCode());
        }
        $this->assertSame(3, $this->db->getConnection()->table('ai_provider_accounts')->count());
        $this->assertSame(3, $this->db->getConnection()->table('ai_agents')->count());
        $this->assertSame(3, $this->db->getConnection()->table('ai_prompts')->count());
    }

    protected function tearDown(): void
    {
        $this->db->getConnection()->disconnect(); Model::clearBootedModels();
        Facade::clearResolvedInstances(); Facade::setFacadeApplication(null); \Mockery::close(); parent::tearDown();
    }
}
