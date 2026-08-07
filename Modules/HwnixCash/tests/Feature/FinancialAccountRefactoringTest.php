<?php

namespace Modules\HwnixCash\tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashFinancialAccount;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessage;
use Modules\HwnixCash\Models\HwnixCashMessageSource;
use Tests\TestCase;

class FinancialAccountRefactoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->user->active_company_id = $this->company->id;
        $this->actingAs($this->user);
    }

    public function test_can_create_financial_account_and_auto_creates_message_source(): void
    {
        $device = HwnixCashDevice::create([
            'company_id' => $this->company->id,
            'android_id' => 'ANDROID_TEST_123',
            'device_name' => 'Test Phone',
        ]);

        $line = HwnixCashLine::create([
            'company_id' => $this->company->id,
            'device_android_id' => $device->android_id,
            'phone_number' => '01012345678',
            'slot_index' => 0,
            'carrier' => 'Vodafone',
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'فودافون كاش - الكشك',
            'line_id' => $line->id,
            'sender_identifier' => 'VF-Cash',
            'account_number' => '01012345678',
            'daily_withdraw_limit' => 50000,
            'daily_deposit_limit' => 50000,
        ];

        $response = $this->postJson('/api/v1/hwnix-cash/financial-accounts', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('hwnix_cash_message_sources', [
            'company_id' => $this->company->id,
            'sender_identifier' => 'VF-Cash',
        ]);

        $this->assertDatabaseHas('hwnix_cash_financial_accounts', [
            'company_id' => $this->company->id,
            'line_id' => $line->id,
            'name' => 'فودافون كاش - الكشك',
            'account_number' => '01012345678',
        ]);
    }

    public function test_distinct_senders_endpoint(): void
    {
        HwnixCashMessage::create([
            'company_id' => $this->company->id,
            'phone_number' => 'VF-Cash',
            'message_body' => 'Test 1',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        HwnixCashMessage::create([
            'company_id' => $this->company->id,
            'phone_number' => 'InstaPay',
            'message_body' => 'Test 2',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $response = $this->getJson('/api/v1/hwnix-cash/financial-accounts/distinct-senders');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonFragment(['VF-Cash'])
            ->assertJsonFragment(['InstaPay']);
    }

    public function test_reconcile_financial_account(): void
    {
        $device = HwnixCashDevice::create([
            'company_id' => $this->company->id,
            'android_id' => 'ANDROID_TEST_999',
            'device_name' => 'Test Phone 2',
        ]);

        $line = HwnixCashLine::create([
            'company_id' => $this->company->id,
            'device_android_id' => $device->android_id,
            'phone_number' => '01012345678',
            'slot_index' => 0,
            'status' => 'active',
        ]);

        $source = HwnixCashMessageSource::create([
            'company_id' => $this->company->id,
            'sender_identifier' => 'VF-Cash',
            'provider' => 'vodafone_cash',
            'is_active' => true,
        ]);

        $account = HwnixCashFinancialAccount::create([
            'company_id' => $this->company->id,
            'line_id' => $line->id,
            'message_source_id' => $source->id,
            'name' => 'فودافون كاش الرئيسي',
            'balance' => 1000.00,
            'actual_balance' => 1500.00,
            'status' => 'active',
        ]);

        // تجربة تنفيذ التسوية بدون سبب ويجب أن تفشل بترجيع 422
        $failResponse = $this->postJson("/api/v1/hwnix-cash/financial-accounts/{$account->id}/reconcile", []);
        $failResponse->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        // تنفيذ التسوية بنجاح عند توفر سبب التسوية
        $response = $this->postJson("/api/v1/hwnix-cash/financial-accounts/{$account->id}/reconcile", [
            'reason' => 'تسوية بعد مراجعة كشف المحفظة',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.balance', 1500);

        $this->assertDatabaseHas('hwnix_cash_wallet_transactions', [
            'company_id' => 1,
            'financial_account_id' => $account->id,
            'operation_type' => 'reconciliation',
            'amount' => 500.00,
            'balance_after' => 1500.00,
        ]);
    }

    public function test_reparsing_balance_inquiry_updates_actual_balance(): void
    {
        $device = HwnixCashDevice::create([
            'company_id' => $this->company->id,
            'android_id' => 'ANDROID_TEST_555',
            'device_name' => 'Test Phone 3',
        ]);

        $line = HwnixCashLine::create([
            'company_id' => $this->company->id,
            'device_android_id' => $device->android_id,
            'phone_number' => '01012345678',
            'slot_index' => 0,
            'status' => 'active',
        ]);

        $source = HwnixCashMessageSource::create([
            'company_id' => $this->company->id,
            'sender_identifier' => 'VF-Cash',
            'provider' => 'vodafone_cash',
            'is_active' => true,
        ]);

        $account = HwnixCashFinancialAccount::create([
            'company_id' => $this->company->id,
            'line_id' => $line->id,
            'message_source_id' => $source->id,
            'name' => 'محفظة فودافون كاش',
            'balance' => 0.00,
            'actual_balance' => 0.00,
            'status' => 'active',
        ]);

        $message = HwnixCashMessage::create([
            'company_id' => $this->company->id,
            'sms_device_id' => $device->id,
            'sms_line_id' => $line->id,
            'phone_number' => 'VF-Cash',
            'message_body' => 'رصيد حسابك فى فودافون كاش الحالي 1339.50 جنيه؛ تاريخ العملية 23:31 26-07-30 رقم العملية 022215083304.',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $mockEngine = \Mockery::mock(\Modules\AiPlatform\Contracts\Engines\AnalysisEngineInterface::class);
        $mockEngine->shouldReceive('analyze')->andReturn(new \Modules\AiPlatform\DTOs\AnalysisResultDTO(
            resultId: 1,
            correlationId: 'test-corr-id',
            analysisType: 'financial_sms',
            messageType: 'balance_inquiry',
            isValid: true,
            isTransaction: false,
            amount: null,
            fee: 0.0,
            currency: 'EGP',
            targetPhone: null,
            targetName: null,
            transactionId: null,
            datetime: '2026-07-30 23:31:00',
            balanceFound: true,
            availableBalance: 1339.50,
            confidenceScore: 100,
            schemaVersion: '1.0',
            promptVersion: '1.0',
            parserVersion: '1.0.0',
            validationErrors: [],
            executionMetadata: [],
            normalizedJson: []
        ));
        $this->app->instance(\Modules\AiPlatform\Contracts\Engines\AnalysisEngineInterface::class, $mockEngine);

        $response = $this->postJson("/api/v1/hwnix-cash/messages/{$message->id}/reparse");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('hwnix_cash_financial_accounts', [
            'id' => $account->id,
            'actual_balance' => 1339.50,
        ]);
    }

    public function test_limit_alerts_endpoint_and_custom_thresholds(): void
    {
        $device = HwnixCashDevice::create([
            'company_id' => $this->company->id,
            'android_id' => 'ANDROID_ALERT_TEST',
            'device_name' => 'Alert Test Phone',
        ]);

        $line = HwnixCashLine::create([
            'company_id' => $this->company->id,
            'device_android_id' => $device->android_id,
            'phone_number' => '01099998888',
            'slot_index' => 0,
            'status' => 'active',
        ]);

        $source = HwnixCashMessageSource::create([
            'company_id' => $this->company->id,
            'sender_identifier' => 'VF-Cash',
            'provider' => 'vodafone_cash',
            'is_active' => true,
        ]);

        $account = HwnixCashFinancialAccount::create([
            'company_id' => $this->company->id,
            'line_id' => $line->id,
            'message_source_id' => $source->id,
            'name' => 'حساب اختبار التنبيهات',
            'daily_withdraw_limit' => 10000.00,
            'daily_withdraw_alert_type' => 'amount',
            'daily_withdraw_alert_value' => 5000.00,
            'status' => 'active',
        ]);

        // 1. استدعاء Endpoint للتنبيهات الحالية ويجب أن يكون فارغاً لعدم وجود استهلاك
        $res1 = $this->getJson('/api/v1/hwnix-cash/financial-accounts/limit-alerts');
        $res1->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // 2. تسجيل معاملة سحب ناجحة بقيمة 6000 EGP وتتجاوز مبلغ التنبيه (5000 EGP)
        \Modules\HwnixCash\Models\HwnixCashWalletTransaction::create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'financial_account_id' => $account->id,
            'line_id' => $line->id,
            'operation_type' => 'cash_withdraw',
            'provider' => 'vodafone_cash',
            'status' => 'success',
            'amount' => 6000.00,
            'currency' => 'EGP',
            'operation_number' => 'TEST-ALERT-001',
            'operation_at' => now(),
            'raw_sms' => 'سحب نقدي بقيمة 6000 جنيه',
        ]);

        // 3. استدعاء Endpoint التنبيهات ويجب أن يُرجع الحساب ومحتوى التنبيه
        $res2 = $this->getJson('/api/v1/hwnix-cash/financial-accounts/limit-alerts');
        $res2->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $account->id)
            ->assertJsonPath('data.0.has_any_alert_triggered', true)
            ->assertJsonPath('data.0.triggered_alerts.0.limit_key', 'daily_withdraw')
            ->assertJsonPath('data.0.triggered_alerts.0.alert_type', 'amount')
            ->assertJsonPath('data.0.triggered_alerts.0.alert_value', 5000);
    }
}
