<?php
// اختبارات الوحدة الخاصة بإجراء إنشاء معاملة محفظة إلكترونية CreateWalletTransactionAction.

namespace Modules\HwnixCash\tests\Unit;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HwnixCash\Actions\CreateWalletTransactionAction;
use Modules\HwnixCash\DTOs\WalletTransactionData;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Repositories\Eloquent\EloquentHwnixCashWalletTransactionRepository;
use Tests\TestCase;

class WalletTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_wallet_transaction_action_persists_raw_sms_and_metadata(): void
    {
        $company = Company::create([
            'id' => 1,
            'name' => 'شركة كاش اختبار وحدة',
            'email' => 'unitwallet@example.com',
            'phone' => '0511112222'
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'full_name' => 'مستخدم وحدة المحافظ',
            'nickname' => 'وحدة',
            'phone' => '0522223333',
            'password' => bcrypt('password')
        ]);

        $device = HwnixCashDevice::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'android_id' => 'android-unit-w1',
            'uuid' => 'uuid-unit-w1',
            'device_name' => 'Unit Phone',
            'brand' => 'Brand',
            'model' => 'Model',
            'android_version' => '13',
            'app_version' => '1.0.0',
            'status' => 'active'
        ]);

        $line = HwnixCashLine::create([
            'device_android_id' => $device->android_id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'slot_index' => 0,
            'subscription_id' => 'sub-w-1',
            'carrier' => 'InstaPay',
            'phone_number' => '01200001111',
            'status' => 'active'
        ]);

        $dto = new WalletTransactionData(
            lineId: $line->id,
            operationType: 'transfer',
            provider: 'instapay',
            status: 'success',
            source: 'api',
            amount: 2500.00,
            fee: 0.00,
            balanceAfter: 12500.00,
            currency: 'EGP',
            operationNumber: 'TXN-INSTA-100',
            operationAt: now()->toIso8601String(),
            targetPhone: '01099998888',
            targetName: 'محمود السيد',
            billNumber: null,
            rawSms: 'تم تحويل 2500 جنيه عبر انستاباي إلى محمود السيد',
            metadata: ['channel' => 'mobile_app']
        );

        $repo = new EloquentHwnixCashWalletTransactionRepository();
        $action = new CreateWalletTransactionAction($repo);

        $entity = $action->execute($dto, $company->id, $user->id);

        $this->assertNotNull($entity->id);
        $this->assertEquals(2500.00, $entity->amount);
        $this->assertEquals('EGP', $entity->currency);
        $this->assertEquals('instapay', $entity->provider->value);
        $this->assertEquals('transfer', $entity->operationType->value);
        $this->assertDatabaseHas('hwnix_cash_wallet_transactions', [
            'id' => $entity->id,
            'raw_sms' => 'تم تحويل 2500 جنيه عبر انستاباي إلى محمود السيد'
        ]);
    }
}
