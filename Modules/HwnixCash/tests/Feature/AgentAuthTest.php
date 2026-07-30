<?php
// اختبارات واجهات المصادقة وتوليد وتجديد الرموز الخاصة بتطبيق أندرويد كاش هونكس.

namespace Modules\HwnixCash\tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AgentAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        Company::create([
            'id' => 1,
            'name' => 'شركة الاختيار كاش هونكس',
            'email' => 'info@example.com',
            'phone' => '0500000000',
        ]);
    }

    protected function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        if (config('permission.teams')) {
            setPermissionsTeamId(1);
        }
        foreach (config('permissions_keys', []) as $entity => $actions) {
            foreach ($actions as $key => $actionData) {
                if ($key === 'name') continue;
                if (isset($actionData['key'])) {
                    Permission::firstOrCreate(
                        ['name' => $actionData['key']],
                        ['guard_name' => 'web']
                    );
                }
            }
        }
    }

    /**
     * اختبار تسجيل حساب جديد بنجاح من تطبيق الأندرويد لكاش هونكس.
     */
    public function test_agent_can_register_successfully(): void
    {
        $payload = [
            'company_name' => 'شركة أندرويد كاش للتجربة',
            'full_name' => 'محمد أحمد',
            'nickname' => 'أبو أحمد',
            'phone' => '0599123456',
            'email' => 'agent1@example.com',
            'password' => 'password123',
            'device_uuid' => 'uuid-test-123456',
        ];

        $response = $this->postJson('/api/v1/agent/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'phone'],
                    'company' => ['id']
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '0599123456',
            'full_name' => 'محمد أحمد'
        ]);
    }

    /**
     * اختبار تسجيل الدخول بنجاح وتوليد Token مخصص لكاش هونكس.
     */
    public function test_agent_can_login_with_phone_and_password(): void
    {
        User::create([
            'company_id' => 1,
            'full_name' => 'علي حسن',
            'nickname' => 'أبو علي',
            'phone' => '0599887766',
            'email' => 'ali@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $payload = [
            'login' => '0599887766',
            'password' => 'secret123',
            'device_uuid' => 'uuid-test-login-99',
        ];

        $response = $this->postJson('/api/v1/agent/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['token', 'user', 'company']]);
    }

    /**
     * اختبار فشل تسجيل الدخول عند استخدام كلمة مرور خاطئة.
     */
    public function test_agent_login_fails_with_invalid_credentials(): void
    {
        User::create([
            'company_id' => 1,
            'full_name' => 'سارة علي',
            'nickname' => 'أم سارة',
            'phone' => '0599000111',
            'password' => bcrypt('correct_password'),
        ]);

        $payload = [
            'login' => '0599000111',
            'password' => 'wrong_password',
            'device_uuid' => 'uuid-test-fail',
        ];

        $response = $this->postJson('/api/v1/agent/auth/login', $payload);

        $response->assertStatus(421)
            ->assertJsonPath('status', false);
    }
}
