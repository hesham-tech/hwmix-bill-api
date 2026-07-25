<?php
// اختبارات إدارة تسجيل أجهزة كاش هونكس ومزامنة الشرائح الـ SIM ونبضات التشغيل للهواتف.

namespace Modules\HwnixCash\tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AgentDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $company = Company::create([
            'id' => 1,
            'name' => 'شركة أجهزة كاش هونكس',
            'email' => 'company@example.com',
            'phone' => '0511111111',
        ]);

        $this->user = User::create([
            'company_id' => $company->id,
            'full_name' => 'مستخدم الـ Agent',
            'nickname' => 'فحص الأجهزة',
            'phone' => '0555555555',
            'password' => bcrypt('password'),
        ]);

        if (config('permission.teams')) {
            setPermissionsTeamId($company->id);
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
     * اختبار تسجيل وتحديث هاتف كاش هونكس وإصدار إعداداته الافتراضية.
     */
    public function test_can_register_device(): void
    {
        $payload = [
            'android_id' => 'android-unique-id-100',
            'uuid' => 'uuid-device-100',
            'device_name' => 'Samsung Galaxy S22',
            'brand' => 'Samsung',
            'model' => 'SM-G991B',
            'android_version' => '13',
            'app_version' => '1.0.0',
            'capabilities' => ['SEND_SMS', 'READ_SMS'],
        ];

        $response = $this->postJson('/api/v1/agent/device/register', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['device_id', 'status']]);

        $this->assertDatabaseHas('hwnix_cash_devices', [
            'android_id' => 'android-unique-id-100',
            'device_name' => 'Samsung Galaxy S22'
        ]);

        $this->assertDatabaseHas('hwnix_cash_device_settings', [
            'sms_device_id' => $response->json('data.device_id')
        ]);
    }

    /**
     * اختبار مزامنة شرائح الـ SIM المتاحة بالهاتف.
     */
    public function test_can_sync_sim_lines(): void
    {
        $device = HwnixCashDevice::create([
            'company_id' => $this->user->company_id,
            'created_by' => $this->user->id,
            'android_id' => 'android-id-sim-test',
            'uuid' => 'uuid-sim-test',
            'device_name' => 'Redmi Note 11',
            'brand' => 'Xiaomi',
            'model' => 'Redmi Note 11',
            'android_version' => '12',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

        $payload = [
            'device_id' => $device->id,
            'device_name' => 'Redmi Note 11 (الفرع الرئيسي)',
            'sims' => [
                [
                    'slot_index' => 0,
                    'subscription_id' => 'sub-111',
                    'carrier' => 'STC',
                    'phone_number' => '0501234567',
                    'network_type' => '4G',
                    'signal_strength' => 4,
                ],
                [
                    'slot_index' => 1,
                    'subscription_id' => 'sub-222',
                    'carrier' => 'Mobily',
                    'phone_number' => '0567654321',
                    'network_type' => '5G',
                    'signal_strength' => 5,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/agent/device/sync-lines', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('hwnix_cash_lines', [
            'device_android_id' => 'android-id-sim-test',
            'phone_number' => '0501234567',
            'carrier' => 'STC'
        ]);
    }

    /**
     * اختبار تسجيل نبضة القلب وتحديث وقت آخر تواجد لجهاز الأندرويد.
     */
    public function test_can_record_device_heartbeat(): void
    {
        $device = HwnixCashDevice::create([
            'company_id' => $this->user->company_id,
            'created_by' => $this->user->id,
            'android_id' => 'android-id-hb',
            'uuid' => 'uuid-hb',
            'device_name' => 'Pixel 7',
            'brand' => 'Google',
            'model' => 'Pixel 7',
            'android_version' => '14',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

        $payload = [
            'device_id' => $device->id,
            'network_type' => 'WIFI',
            'battery_level' => 85,
            'is_internet_available' => true,
            'free_memory_bytes' => 1073741824,
            'free_storage_bytes' => 5368709120,
            'app_version' => '1.0.0',
            'configuration_version' => 1,
        ];

        $response = $this->postJson('/api/v1/agent/device/heartbeat', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['settings_updated', 'update_policy']]);

        $this->assertDatabaseHas('hwnix_cash_device_heartbeats', [
            'sms_device_id' => $device->id,
            'battery_level' => 85
        ]);
    }
}
