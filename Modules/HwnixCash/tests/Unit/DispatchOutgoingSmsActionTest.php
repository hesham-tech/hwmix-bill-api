<?php
// اختبارات الوحدة الخاصة بإجراء جدولة وإرسال الرسائل الصادرة DispatchOutgoingSmsAction.

namespace Modules\HwnixCash\tests\Unit;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HwnixCash\Actions\DispatchOutgoingSmsAction;
use Modules\HwnixCash\DTOs\OutgoingSmsData;
use Modules\HwnixCash\Drivers\HwnixCashDriverManager;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashMessageRepository;
use Tests\TestCase;

class DispatchOutgoingSmsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_outgoing_sms_creates_message_and_command(): void
    {
        $company = Company::create([
            'id' => 1,
            'name' => 'شركة الاختيار كاش هونكس',
            'email' => 'unit2@example.com',
            'phone' => '0533333333'
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'full_name' => 'مستخدم الوحدة 2',
            'nickname' => 'وحدة 2',
            'phone' => '0544444444',
            'password' => bcrypt('password')
        ]);

        $device = HwnixCashDevice::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'android_id' => 'android-unit-dispatch',
            'uuid' => 'uuid-unit-dispatch',
            'device_name' => 'Dispatch Phone',
            'brand' => 'Brand',
            'model' => 'Model',
            'android_version' => '12',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

        $line = HwnixCashLine::create([
            'device_android_id' => $device->android_id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'slot_index' => 0,
            'subscription_id' => 'sub-dispatch-1',
            'carrier' => 'STC',
            'phone_number' => '0500001111',
            'status' => 'active'
        ]);

        $dto = new OutgoingSmsData(
            smsLineId: $line->id,
            phoneNumber: '0599001122',
            messageBody: 'نص رسالة اختبار الوحدة كاش هونكس'
        );

        $msgRepo = new EloquentHwnixCashMessageRepository();
        $driverManager = new HwnixCashDriverManager($this->app);
        $action = new DispatchOutgoingSmsAction($msgRepo, $driverManager);

        $messageEntity = $action->execute($dto, $company->id, $user->id);

        $this->assertNotNull($messageEntity->id);
        $this->assertDatabaseHas('hwnix_cash_messages', [
            'id' => $messageEntity->id,
            'direction' => 'outgoing',
            'phone_number' => '0599001122'
        ]);

        $this->assertDatabaseHas('hwnix_cash_device_commands', [
            'sms_device_id' => $device->id,
            'command_type' => 'SEND_SMS'
        ]);
    }
}
