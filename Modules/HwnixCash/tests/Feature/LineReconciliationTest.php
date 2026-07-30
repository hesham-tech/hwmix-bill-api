<?php

namespace Modules\HwnixCash\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HwnixCash\Models\HwnixCashFinancialAccount;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessageSource;
use Modules\HwnixCash\Models\HwnixCashWalletTransaction;
use Tests\TestCase;

class LineReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_reconcile_line_balance_and_record_transaction(): void
    {
        $user = User::factory()->create(['company_id' => 1]);

        $line = HwnixCashLine::create([
            'company_id' => 1,
            'created_by' => $user->id,
            'device_android_id' => 'test-device-id',
            'slot_index' => 0,
            'phone_number' => '01012345678',
            'carrier' => 'Vodafone',
            'status' => 'active',
        ]);

        $source = HwnixCashMessageSource::create([
            'company_id' => 1,
            'created_by' => $user->id,
            'sender_identifier' => 'VF-Cash',
            'provider' => 'vodafone_cash',
            'is_active' => true,
        ]);

        $account = HwnixCashFinancialAccount::create([
            'company_id' => 1,
            'created_by' => $user->id,
            'line_id' => $line->id,
            'message_source_id' => $source->id,
            'name' => 'محفظة فودافون كاش',
            'balance' => 500.00,
            'actual_balance' => 1000.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/hwnix-cash/financial-accounts/{$account->id}/reconcile");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.balance', 1000)
            ->assertJsonPath('data.has_balance_mismatch', false);

        $this->assertDatabaseHas('hwnix_cash_financial_accounts', [
            'id' => $account->id,
            'balance' => 1000.00,
        ]);

        $this->assertDatabaseHas('hwnix_cash_wallet_transactions', [
            'company_id' => 1,
            'financial_account_id' => $account->id,
            'operation_type' => 'reconciliation',
            'amount' => 500.00,
            'balance_after' => 1000.00,
        ]);
    }
}
