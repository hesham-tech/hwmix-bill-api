<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Modules\Accounting\Models\CashBox;
use App\Models\CashBoxType;
use Modules\Accounting\Models\OwnerFundTransaction;
use Modules\Accounting\Models\CashReconciliation;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات وحدة وتكامل لإدارة صلاحيات الخزن، تسويات النقدية، وأموال الملاك والشركاء
 */
class FinancialsCoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected CashBox $cashBox;
    protected CashBoxType $boxType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->admin->givePermissionTo('admin.super');

        $this->boxType = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'نقدي'
        ]);

        $this->cashBox = CashBox::create([
            'name' => 'الخزينة الرئيسية',
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->boxType->id,
            'balance' => 1000.00,
            'is_active' => true,
            'access_type' => 'company_shared',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_can_get_cashboxes_summary()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/v1/cash-boxes/summary');

        $response->assertStatus(200);
        $this->assertEquals(1000, (float)$response->json('data.total_cash'));
        $this->assertEquals(1000, (float)$response->json('data.total_all'));
    }

    public function test_can_manage_cashbox_permissions()
    {
        $this->actingAs($this->admin);

        // Fetch users
        $response = $this->getJson("/api/v1/cash-boxes/{$this->cashBox->id}/users");
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['assigned_user_ids', 'company_users']]);

        // Sync users
        $otherUser = User::factory()->create(['company_id' => $this->company->id]);
        $syncResponse = $this->postJson("/api/v1/cash-boxes/{$this->cashBox->id}/users", [
            'user_ids' => [$otherUser->id]
        ]);

        $syncResponse->assertStatus(200);
        $this->assertDatabaseHas('cash_box_user', [
            'cash_box_id' => $this->cashBox->id,
            'user_id' => $otherUser->id
        ]);
    }

    public function test_can_store_and_approve_reconciliation()
    {
        $this->actingAs($this->admin);

        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'reconciliation_date' => now()->toDateString(),
            'physical_balance' => 950.00,
            'notes' => 'عجز بسيط في النقدية الفردية',
        ];

        $response = $this->postJson('/api/v1/cash-reconciliations', $payload);

        $response->assertStatus(201);
        $this->assertEquals(1000, (float)$response->json('data.book_balance'));
        $this->assertEquals(-50, (float)$response->json('data.difference'));
        $this->assertEquals('pending', $response->json('data.status'));

        $reconcileId = $response->json('data.id');

        $approveResponse = $this->postJson("/api/v1/cash-reconciliations/{$reconcileId}/approve");
        $approveResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_can_store_owner_fund_transactions_with_ledger()
    {
        $this->actingAs($this->admin);

        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->admin->id,
            'type' => 'capital_increase',
            'amount' => 5000.00,
            'entry_date' => now()->toDateString(),
            'description' => 'زيادة مساهمة الشريك المؤسس نقداً',
        ];

        $response = $this->postJson('/api/v1/owner-fund-transactions', $payload);

        if ($response->status() !== 201) {
            $response->dump();
        }

        $response->assertStatus(201);
        $this->assertEquals(6000.00, $this->cashBox->fresh()->balance);

        // Check if double entry was posted
        $this->assertDatabaseHas('financial_ledger', [
            'company_id' => $this->company->id,
            'account_type' => 'equity',
            'amount' => 5000.00,
            'type' => 'credit'
        ]);
        $this->assertDatabaseHas('financial_ledger', [
            'company_id' => $this->company->id,
            'account_type' => 'asset',
            'amount' => 5000.00,
            'type' => 'debit'
        ]);
    }
}
