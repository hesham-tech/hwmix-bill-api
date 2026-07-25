<?php
// اختبارات واجهات لوحة تحكم الويب كاش هونكس HwnixCash لإدارة الأجهزة والشرائح وبث الرسائل.

namespace Modules\HwnixCash\tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebHwnixCashTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->company = Company::create([
            'id' => 1,
            'name' => 'شركة كاش هونكس ERP',
            'email' => 'web@example.com',
            'phone' => '0533333333',
        ]);

        $this->adminUser = User::create([
            'company_id' => $this->company->id,
            'full_name' => 'مدير كاش هونكس',
            'nickname' => 'الأدمن',
            'phone' => '0566666666',
            'password' => bcrypt('password'),
        ]);

        if (config('permission.teams')) {
            setPermissionsTeamId($this->company->id);
        }
        $this->adminUser->givePermissionTo(perm_key('admin.super'));

        Sanctum::actingAs($this->adminUser);
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
     * اختبار جلب قائمة أجهزة كاش هونكس عبر واجهات الويب.
     */
    public function test_can_fetch_web_devices_list(): void
    {
        HwnixCashDevice::create([
            'company_id' => $this->company->id,
            'created_by' => $this->adminUser->id,
            'android_id' => 'android-web-dev-1',
            'uuid' => 'uuid-web-dev-1',
            'device_name' => 'Nokia X20',
            'brand' => 'Nokia',
            'model' => 'X20',
            'android_version' => '12',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

        $response = $this->getJson('/api/v1/hwnix-cash/devices');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => [['id', 'device_name', 'status', 'is_online']]]);
    }

    /**
     * اختبار تعديل رصيد وملاحظات شريحة الاتصال الـ SIM.
     */
    public function test_can_update_line_balance_and_notes(): void
    {
        $device = HwnixCashDevice::create([
            'company_id' => $this->company->id,
            'created_by' => $this->adminUser->id,
            'android_id' => 'android-line-test',
            'uuid' => 'uuid-line-test',
            'device_name' => 'Honor 50',
            'brand' => 'Honor',
            'model' => '50',
            'android_version' => '11',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

        $line = HwnixCashLine::create([
            'device_android_id' => $device->android_id,
            'company_id' => $this->company->id,
            'created_by' => $this->adminUser->id,
            'slot_index' => 0,
            'subscription_id' => 'sub-up-1',
            'carrier' => 'Zain',
            'phone_number' => '0590001122',
            'status' => 'active',
            'balance' => 10.00
        ]);

        $updatePayload = [
            'balance' => 150.50,
            'actual_balance' => 150.50,
            'daily_limit' => 300,
            'note' => 'خط مبيعات كاش هونكس',
        ];

        $response = $this->putJson("/api/v1/hwnix-cash/lines/{$line->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('hwnix_cash_lines', [
            'id' => $line->id,
            'balance' => 150.50,
            'note' => 'خط مبيعات كاش هونكس'
        ]);
    }

    /**
     * اختبار إرسال رسالة جديدة عبر لوحة كاش هونكس وجدولتها للجهاز.
     */
    public function test_can_send_outgoing_sms_from_web(): void
    {
        $device = HwnixCashDevice::create([
            'company_id' => $this->company->id,
            'created_by' => $this->adminUser->id,
            'android_id' => 'android-out-web',
            'uuid' => 'uuid-out-web',
            'device_name' => 'Xiaomi 12',
            'brand' => 'Xiaomi',
            'model' => '12',
            'android_version' => '13',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

        $line = HwnixCashLine::create([
            'device_android_id' => $device->android_id,
            'company_id' => $this->company->id,
            'created_by' => $this->adminUser->id,
            'slot_index' => 0,
            'subscription_id' => 'sub-out-1',
            'carrier' => 'STC',
            'phone_number' => '0551122334',
            'status' => 'active'
        ]);

        $sendPayload = [
            'sms_line_id' => $line->id,
            'phone_number' => '0599112233',
            'message_body' => 'تم إصدار فاتورة كاش هونكس رقم 1005',
        ];

        $response = $this->postJson('/api/v1/hwnix-cash/messages/send', $sendPayload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['message_id', 'status']]);

        $this->assertDatabaseHas('hwnix_cash_messages', [
            'company_id' => $this->company->id,
            'sms_line_id' => $line->id,
            'phone_number' => '0599112233',
            'direction' => 'outgoing',
            'status' => 'queued'
        ]);

        $this->assertDatabaseHas('hwnix_cash_device_commands', [
            'sms_device_id' => $device->id,
            'command_type' => 'SEND_SMS'
        ]);
    }
}
