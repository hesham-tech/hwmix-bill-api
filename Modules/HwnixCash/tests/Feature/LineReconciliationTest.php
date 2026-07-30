<?php

namespace Modules\HwnixCash\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HwnixCash\Models\HwnixCashLine;
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
            'carrier' => 'vodafone_cash',
            'balance' => 500.00,
            'actual_balance' => 1000.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/hwnix-cash/lines/{$line->id}/reconcile", [
                'target_balance' => 1000.00,
                'note' => 'مساواة بالرصيد الفعلي',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.balance', 1000)
            ->assertJsonPath('data.has_balance_mismatch', false);

        $this->assertDatabaseHas('hwnix_cash_lines', [
            'id' => $line->id,
            'balance' => 1000.00,
        ]);

        $this->assertDatabaseHas('hwnix_cash_wallet_transactions', [
            'company_id' => 1,
            'line_id' => $line->id,
            'operation_type' => 'reconciliation',
            'amount' => 500.00,
            'balance_after' => 1000.00,
        ]);
    }
}
