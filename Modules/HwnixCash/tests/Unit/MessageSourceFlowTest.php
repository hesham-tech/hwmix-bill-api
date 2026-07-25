<?php
// اختبارات وحدة لتدفق استقبال الرسائل النصية وفحص المصادر المعتمدة وتمريرها لنقطة التوسع.

namespace Modules\HwnixCash\tests\Unit;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HwnixCash\Actions\ProcessIncomingSmsAction;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageParserInterface;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\DTOs\IncomingSmsData;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessageSource;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashMessageRepository;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashMessageSourceRepository;
use Tests\TestCase;

class MessageSourceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unregistered_sender_message_saved_only_without_calling_parser(): void
    {
        $company = Company::create([
            'id' => 1,
            'name' => 'شركة تدفق المصادر 1',
            'email' => 'flow1@example.com',
            'phone' => '0511110000'
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'full_name' => 'مستخدم تدفق 1',
            'nickname' => 'تست 1',
            'phone' => '0522220000',
            'password' => bcrypt('password')
        ]);

        $device = HwnixCashDevice::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'android_id' => 'android-flow-dev-1',
            'uuid' => 'uuid-flow-dev-1',
            'device_name' => 'Flow Phone',
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
            'subscription_id' => 'sub-flow-1',
            'carrier' => 'Vodafone',
            'phone_number' => '01000001111',
            'status' => 'active'
        ]);

        $dto = new IncomingSmsData(
            deviceId: $device->id,
            subscriptionId: 'sub-flow-1',
            phoneNumber: '01099887766', // مرسل عالي غير مسجل كـ MessageSource
            messageBody: 'رسالة شخصية من صديق',
            messageRef: 'ref-flow-unreg-1'
        );

        $parserMock = $this->createMock(HwnixCashMessageParserInterface::class);
        $parserMock->expects($this->never())->method('parse');

        $msgRepo = new EloquentHwnixCashMessageRepository();
        $sourceRepo = new EloquentHwnixCashMessageSourceRepository();

        $action = new ProcessIncomingSmsAction($msgRepo, $sourceRepo, $parserMock);
        $message = $action->execute($dto, $company->id, $user->id);

        $this->assertNotNull($message->id);
        $this->assertDatabaseHas('hwnix_cash_messages', [
            'id' => $message->id,
            'phone_number' => '01099887766'
        ]);
    }

    public function test_registered_active_sender_message_saved_and_passed_to_parser_extension_point(): void
    {
        $company = Company::create([
            'id' => 1,
            'name' => 'شركة تدفق المصادر 2',
            'email' => 'flow2@example.com',
            'phone' => '0533330000'
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'full_name' => 'مستخدم تدفق 2',
            'nickname' => 'تست 2',
            'phone' => '0544440000',
            'password' => bcrypt('password')
        ]);

        $device = HwnixCashDevice::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'android_id' => 'android-flow-dev-2',
            'uuid' => 'uuid-flow-dev-2',
            'device_name' => 'Flow Phone 2',
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
            'subscription_id' => 'sub-flow-2',
            'carrier' => 'Vodafone',
            'phone_number' => '01000002222',
            'status' => 'active'
        ]);

        // تسجيل مصدر معتمد ومفعل
        HwnixCashMessageSource::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'sender_identifier' => 'VF-Cash',
            'provider' => 'vodafone_cash',
            'is_active' => true,
        ]);

        $dto = new IncomingSmsData(
            deviceId: $device->id,
            subscriptionId: 'sub-flow-2',
            phoneNumber: 'VF-Cash', // مرسل معتمد ومفعل
            messageBody: 'تم استلام مبلغ 500 جنيه من فودافون كاش',
            messageRef: 'ref-flow-reg-2'
        );

        $parserMock = $this->createMock(HwnixCashMessageParserInterface::class);
        $parserMock->expects($this->once())
            ->method('parse')
            ->with($this->isInstanceOf(SmsMessage::class));

        $msgRepo = new EloquentHwnixCashMessageRepository();
        $sourceRepo = new EloquentHwnixCashMessageSourceRepository();

        $action = new ProcessIncomingSmsAction($msgRepo, $sourceRepo, $parserMock);
        $message = $action->execute($dto, $company->id, $user->id);

        $this->assertNotNull($message->id);
        $this->assertDatabaseHas('hwnix_cash_messages', [
            'id' => $message->id,
            'phone_number' => 'VF-Cash'
        ]);
    }
}
