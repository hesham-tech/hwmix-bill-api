<?php
// اختبارات الوحدة الخاصة بإجراء تسجيل وتحديث الأجهزة RegisterDeviceAction لكاش هونكس.

namespace Modules\HwnixCash\tests\Unit;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HwnixCash\Actions\RegisterDeviceAction;
use Modules\HwnixCash\DTOs\DeviceData;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashDeviceRepository;
use Tests\TestCase;

class RegisterDeviceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_device_action_creates_device_and_settings(): void
    {
        $company = Company::create([
            'id' => 1,
            'name' => 'شركة كاش هونكس 1',
            'email' => 'unit1@example.com',
            'phone' => '0511111111'
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'full_name' => 'مستخدم الوحدة',
            'nickname' => 'وحدة',
            'phone' => '0522222222',
            'password' => bcrypt('password')
        ]);

        $dto = new DeviceData(
            androidId: 'android-unit-123',
            uuid: 'uuid-unit-123',
            deviceName: 'Test Phone',
            brand: 'TestBrand',
            model: 'TestModel',
            androidVersion: '13',
            appVersion: '1.0.0',
            capabilities: ['SEND_SMS']
        );

        $repo = new EloquentHwnixCashDeviceRepository();
        $action = new RegisterDeviceAction($repo);

        $device = $action->execute($dto, $company->id, $user->id);

        $this->assertNotNull($device->id);
        $this->assertEquals('android-unit-123', $device->androidId);
        $this->assertDatabaseHas('hwnix_cash_devices', ['android_id' => 'android-unit-123']);
        $this->assertDatabaseHas('hwnix_cash_device_settings', ['sms_device_id' => $device->id]);
    }
}
