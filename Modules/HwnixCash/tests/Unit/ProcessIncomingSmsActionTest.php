<?php
// اختبارات الوحدة الخاصة بإجراء معالجة الرسائل الواردة ProcessIncomingSmsAction لكاش هونكس.

namespace Modules\HwnixCash\tests\Unit;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HwnixCash\Actions\ProcessIncomingSmsAction;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageParserInterface;
use Modules\HwnixCash\DTOs\IncomingSmsData;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashMessageRepository;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashMessageSourceRepository;
use Tests\TestCase;

class ProcessIncomingSmsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_incoming_sms_saves_entity(): void
    {
        $company = Company::create([
            'id' => 1,
            'name' => 'شركة كاش هونكس 3',
            'email' => 'unit3@example.com',
            'phone' => '0544444444'
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'full_name' => 'مستخدم الوحدة 3',
            'nickname' => 'وحدة 3',
            'phone' => '0555555555',
            'password' => bcrypt('password')
        ]);

        $device = HwnixCashDevice::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'android_id' => 'android-unit-inc',
            'uuid' => 'uuid-unit-inc',
            'device_name' => 'Incoming Phone',
            'brand' => 'Brand',
            'model' => 'Model',
            'android_version' => '13',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

        HwnixCashLine::create([
            'device_android_id' => $device->android_id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'slot_index' => 0,
            'subscription_id' => 'sub-inc-1',
            'carrier' => 'STC',
            'phone_number' => '0500002222',
            'status' => 'active'
        ]);

        $dto = new IncomingSmsData(
            deviceId: $device->id,
            subscriptionId: 'sub-inc-1',
            phoneNumber: '0566778899',
            messageBody: 'تم استلام تحويل كاش هونكس',
            messageRef: 'ref-inc-unit-1'
        );

        $msgRepo = new EloquentHwnixCashMessageRepository();
        $sourceRepo = new EloquentHwnixCashMessageSourceRepository();
        $parserMock = $this->createMock(HwnixCashMessageParserInterface::class);

        $action = new ProcessIncomingSmsAction($msgRepo, $sourceRepo, $parserMock);

        $message = $action->execute($dto, $company->id, $user->id);

        $this->assertNotNull($message->id);
        $this->assertEquals('incoming', $message->direction);
        $this->assertDatabaseHas('hwnix_cash_messages', [
            'id' => $message->id,
            'message_ref' => 'ref-inc-unit-1'
        ]);
    }
}
