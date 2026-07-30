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

        $response = $this->postJson("/api/v1/hwnix-cash/financial-accounts/{$account->id}/reconcile");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.balance', 1500);

        $this->assertDatabaseHas('hwnix_cash_wallet_transactions', [
            'financial_account_id' => $account->id,
            'operation_type' => 'reconciliation',
            'amount' => 500.00,
        ]);
    }
}
