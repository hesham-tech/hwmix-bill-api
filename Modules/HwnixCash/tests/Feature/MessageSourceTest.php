<?php
// اختبارات واجهات وميزات إدارة مصادر الرسائل المعتمدة بكاش هونكس.

namespace Modules\HwnixCash\tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\HwnixCash\Models\HwnixCashMessageSource;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MessageSourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->company = Company::create([
            'id' => 1,
            'name' => 'شركة مصادر الرسائل',
            'email' => 'sources@example.com',
            'phone' => '0599000111',
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'full_name' => 'مدير المصادر',
            'nickname' => 'مسؤول الرسائل',
            'phone' => '0577777777',
            'password' => bcrypt('password'),
        ]);

        if (config('permission.teams')) {
            setPermissionsTeamId($this->company->id);
        }
        $this->user->givePermissionTo(perm_key('admin.super'));

        Sanctum::actingAs($this->user);
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
     * اختبار إضافة مصدر رسائل معتمد جديد (مثل VF-Cash أو CIB).
     */
    public function test_can_create_message_source(): void
    {
        $payload = [
            'sender_identifier' => 'VF-Cash',
            'provider' => 'vodafone_cash',
            'is_active' => true,
            'description' => 'رسائل فودافون كاش الرسمية',
        ];

        $response = $this->postJson('/api/v1/hwnix-cash/message-sources', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.sender_identifier', 'VF-Cash')
            ->assertJsonPath('data.provider', 'vodafone_cash');

        $this->assertDatabaseHas('hwnix_cash_message_sources', [
            'company_id' => $this->company->id,
            'sender_identifier' => 'VF-Cash'
        ]);
    }

    /**
     * اختبار جلب وتعديل مصادر الرسائل المعتمدة.
     */
    public function test_can_fetch_and_update_message_sources(): void
    {
        $source = HwnixCashMessageSource::create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'sender_identifier' => 'Orange Cash',
            'provider' => 'orange_cash',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hwnix-cash/message-sources');
        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $updatePayload = [
            'sender_identifier' => 'Orange Cash Official',
            'provider' => 'orange_cash',
            'is_active' => false,
            'description' => 'موقف مؤقتاً'
        ];

        $updateResponse = $this->putJson("/api/v1/hwnix-cash/message-sources/{$source->id}", $updatePayload);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('hwnix_cash_message_sources', [
            'id' => $source->id,
            'sender_identifier' => 'Orange Cash Official',
            'is_active' => false
        ]);
    }
}
