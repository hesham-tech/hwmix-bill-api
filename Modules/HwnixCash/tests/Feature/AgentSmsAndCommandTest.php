<?php
// اختبارات معالجة الأوامر التشغيلية والرسائل الواردة والصادرة لكاش هونكس HwnixCash.

namespace Modules\HwnixCash\tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashDeviceCommand;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AgentSmsAndCommandTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected HwnixCashDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $company = Company::create([
            'id' => 1,
            'name' => 'شركة المبيعات كاش هونكس',
            'email' => 'sales@example.com',
            'phone' => '0522222222',
        ]);

        $this->user = User::create([
            'company_id' => $company->id,
            'full_name' => 'أحمد خالد',
            'nickname' => 'مدير الرسائل',
            'phone' => '0544444444',
            'password' => bcrypt('password'),
        ]);

        if (config('permission.teams')) {
            setPermissionsTeamId($company->id);
        }
        $this->user->givePermissionTo(perm_key('admin.super'));

        $this->device = HwnixCashDevice::create([
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'android_id' => 'android-cmd-sms-1',
            'uuid' => 'uuid-cmd-sms-1',
            'device_name' => 'Galaxy Note 20',
            'brand' => 'Samsung',
            'model' => 'SM-N980F',
            'android_version' => '13',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

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
     * اختبار استلام وحفظ رسالة واردة جديدة من الهاتف والتحقق من الـ Idempotency لكاش هونكس.
     */
    public function test_can_process_incoming_sms(): void
    {
        HwnixCashLine::create([
            'device_android_id' => $this->device->android_id,
            'company_id' => $this->user->company_id,
            'created_by' => $this->user->id,
            'slot_index' => 0,
            'subscription_id' => 'sub-incoming-1',
            'carrier' => 'STC',
            'phone_number' => '0550001122',
            'status' => 'active'
        ]);

        $payload = [
            'device_id' => $this->device->id,
            'subscription_id' => 'sub-incoming-1',
            'phone_number' => '0599998877',
            'message_body' => 'مرحباً، تم تحويل المبلغ',
            'message_ref' => 'ref-local-msg-999',
            'sent_at' => now()->toIso8601String(),
        ];

        $response = $this->postJson('/api/v1/agent/sms/incoming', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['message_id', 'status']]);

        $this->assertDatabaseHas('hwnix_cash_messages', [
            'sms_device_id' => $this->device->id,
            'phone_number' => '0599998877',
            'direction' => 'incoming',
            'message_ref' => 'ref-local-msg-999'
        ]);

        // اختبار عدم تكرار الرسالة عند إعادة الإرسال بنفس الـ message_ref
        $duplicateResponse = $this->postJson('/api/v1/agent/sms/incoming', $payload);
        $duplicateResponse->assertStatus(200);
        $this->assertEquals(1, HwnixCashMessage::where('message_ref', 'ref-local-msg-999')->count());
    }

    /**
     * اختبار جلب الأوامر المعلقة وتنفيذها لكاش هونكس.
     */
    public function test_can_fetch_and_execute_pending_command(): void
    {
        $command = HwnixCashDeviceCommand::create([
            'sms_device_id' => $this->device->id,
            'command_type' => 'SEND_SMS',
            'payload' => [
                'message_id' => 50,
                'phone_number' => '0500000000',
                'message_body' => 'رسالة تجريبية للأمر',
                'slot_index' => 0,
                'subscription_id' => 'sub-1',
            ],
            'status' => 'pending',
            'idempotency_key' => 'key-cmd-50'
        ]);

        // 1. جلب الأوامر المعلقة
        $fetchResponse = $this->getJson("/api/v1/agent/commands/pending?device_id={$this->device->id}");
        $fetchResponse->assertStatus(200)
            ->assertJsonPath('status', true);

        // 2. تنفيذ الأمر
        $executePayload = [
            'device_id' => $this->device->id,
            'status' => 'executed',
            'response_payload' => ['result' => 'OK']
        ];

        $execResponse = $this->postJson("/api/v1/agent/commands/{$command->id}/execute", $executePayload);
        $execResponse->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('hwnix_cash_device_commands', [
            'id' => $command->id,
            'status' => 'executed'
        ]);
    }
}
