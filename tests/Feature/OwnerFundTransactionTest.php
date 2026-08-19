<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\FinancialOperation;
use Modules\Accounting\Models\OwnerFundTransaction;
use Modules\Companies\Models\StakeholderFinancialBalance;

class OwnerFundTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $partner;
    protected Company $company;
    protected CashBox $cashBox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AddPermissionsSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
        ]);
        
        setPermissionsTeamId($this->company->id);

        $this->admin->givePermissionTo(perm_key('admin.super'));
        $this->admin->givePermissionTo(perm_key('owner_fund_transactions.view_all'));
        $this->admin->givePermissionTo(perm_key('owner_fund_transactions.create'));
        $this->admin->givePermissionTo(perm_key('owner_fund_transactions.reverse'));

        $this->partner = User::factory()->create([
            'company_id' => $this->company->id,
            'nickname' => 'Partner Ahmed',
        ]);

        $boxType = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Cash',
        ]);

        $this->cashBox = CashBox::create([
            'name' => 'Main Partner Safe',
            'company_id' => $this->company->id,
            'cash_box_type_id' => $boxType->id,
            'balance' => 10000.00,
            'is_active' => true,
            'access_type' => 'company_shared',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_capital_increase_creates_operation_and_balance()
    {
        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'capital_increase',
            'amount' => 5000.00,
            'entry_date' => now()->toIso8601String(),
            'description' => 'Initial Capital',
        ];

        // The route might be /api/owner-fund-transactions depending on the routing.
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/owner-fund-transactions', $payload); // Note: verify exact route

        $response->assertStatus(201);
        $txId = $response->json('data.id');

        // Check OwnerFundTransaction exists
        $this->assertDatabaseHas('owner_fund_transactions', [
            'id' => $txId,
            'type' => 'capital_increase',
            'amount' => 5000.00,
        ]);

        // Check FinancialOperation exists and links to OwnerFundTransaction
        $this->assertDatabaseHas('financial_operations', [
            'source_type' => OwnerFundTransaction::class,
            'source_id' => $txId,
            'type' => 'partner_fund',
            'status' => 'completed',
        ]);

        $op = FinancialOperation::where('source_type', OwnerFundTransaction::class)
            ->where('source_id', $txId)->first();

        // Check Transaction exists
        $this->assertDatabaseHas('transactions', [
            'financial_operation_id' => $op->id,
            'cashbox_id' => $this->cashBox->id,
            'type' => 'deposit',
            'amount' => 5000.00,
        ]);

        // Check StakeholderFinancialBalance exists (capital)
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'capital',
            'balance' => 5000.00,
        ]);
    }

    public function test_reversing_partner_fund_transaction()
    {
        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'capital_increase',
            'amount' => 5000.00,
            'entry_date' => now()->toIso8601String(),
            'description' => 'Initial Capital',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/owner-fund-transactions', $payload);

        $response->assertStatus(201);
        $txId = $response->json('data.id');

        $op = FinancialOperation::where('source_type', OwnerFundTransaction::class)
            ->where('source_id', $txId)->first();

        // Check initial state
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'capital',
            'balance' => 5000.00,
        ]);
        
        $this->actingAs($this->admin)->postJson("/api/v1/owner-fund-transactions/{$txId}/reverse")->dump()->assertStatus(200);

        // Check it was reversed in stakeholder_financial_balances
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'capital',
            'balance' => 0.00,
        ]);
        
        $this->assertDatabaseHas('financial_operations', [
            'id' => $op->id,
            'status' => 'reversed',
        ]);
    }

    public function test_partner_drawing()
    {
        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'drawings',
            'amount' => 1000.00,
            'entry_date' => now()->toIso8601String(),
            'description' => 'Drawing',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/owner-fund-transactions', $payload);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'partner_drawing',
            'balance' => 1000.00,
        ]);
        
        $this->assertDatabaseHas('financial_ledger', [
            'account_type' => 'equity',
            'type' => 'debit',
            'amount' => 1000.00,
        ]);
    }

    public function test_partner_loan()
    {
        $payload1 = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'loan_from_owner',
            'amount' => 10000.00,
            'entry_date' => now()->toIso8601String(),
        ];
        $response = $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload1);
        if ($response->status() !== 201) {
            $response->dump();
        }
        $response->assertStatus(201);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'partner_loan',
            'balance' => 10000.00,
        ]);

        $payload2 = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'loan_to_owner',
            'amount' => 3000.00,
            'entry_date' => now()->toIso8601String(),
        ];
        $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload2)->assertStatus(201);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'partner_loan',
            'balance' => 7000.00,
        ]);
    }

    public function test_partner_advance()
    {
        $payload1 = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'advance_from_owner',
            'amount' => 5000.00,
            'entry_date' => now()->toIso8601String(),
        ];
        $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload1)->assertStatus(201);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'partner_advance',
            'balance' => 5000.00,
        ]);

        $payload2 = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'advance_to_partner',
            'amount' => 1000.00,
            'entry_date' => now()->toIso8601String(),
        ];
        $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload2)->assertStatus(201);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'partner_advance',
            'balance' => 4000.00,
        ]);
    }

    public function test_reversal_isolation()
    {
        StakeholderFinancialBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->partner->id,
            'relation_type' => 'capital',
            'balance' => 50000.00
        ]);

        StakeholderFinancialBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->partner->id,
            'relation_type' => 'partner_loan',
            'balance' => 20000.00
        ]);

        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'loan_from_owner',
            'amount' => 5000.00,
            'entry_date' => now()->toIso8601String(),
        ];
        $response = $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload);
        $txId = $response->json('data.id');
        
        $op = FinancialOperation::where('source_type', OwnerFundTransaction::class)
            ->where('source_id', $txId)->first();

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'partner_loan',
            'balance' => 25000.00,
        ]);

        $this->actingAs($this->admin)->postJson("/api/v1/owner-fund-transactions/{$txId}/reverse")->dump()->assertStatus(200);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'capital',
            'balance' => 50000.00,
        ]);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'partner_loan',
            'balance' => 20000.00,
        ]);
    }

    public function test_idempotency_prevents_duplicate_processing()
    {
        $uuid = \Illuminate\Support\Str::uuid()->toString();

        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'capital_increase',
            'amount' => 1000.00,
            'entry_date' => now()->toIso8601String(),
            'idempotency_key' => $uuid
        ];

        $response1 = $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload);
        $response1->assertStatus(201);

        $response2 = $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload);
        $response2->assertStatus(409);

        $this->assertEquals(1, OwnerFundTransaction::where('type', 'capital_increase')->count());
        $this->assertEquals(1, FinancialOperation::where('id', $uuid)->count());
        
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'capital',
            'balance' => 1000.00,
        ]);
    }

    public function test_atomic_rollback_on_failure()
    {
        $this->mock(\App\Contracts\FinancialEngineInterface::class, function ($mock) {
            $mock->shouldReceive('receiveMoney')->andThrow(new \Exception('Simulated Failure'));
        });

        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'capital_increase',
            'amount' => 1000.00,
            'entry_date' => now()->toIso8601String(),
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload);
        $response->assertStatus(500);

        $this->assertDatabaseCount('owner_fund_transactions', 0);
        $this->assertDatabaseCount('financial_operations', 0);
        $this->assertDatabaseMissing('stakeholder_financial_balances', [
            'user_id' => $this->partner->id,
            'relation_type' => 'capital',
        ]);
    }
    public function test_duplicate_reverse_is_prevented()
    {
        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'capital_increase',
            'amount' => 1000.00,
            'entry_date' => now()->toIso8601String(),
        ];
        $response = $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload);
        $txId = $response->json('data.id');

        // First reverse
        $this->actingAs($this->admin)->postJson("/api/v1/owner-fund-transactions/{$txId}/reverse")->dump()->assertStatus(200);

        // Second reverse
        $this->actingAs($this->admin)->postJson("/api/v1/owner-fund-transactions/{$txId}/reverse")->assertStatus(422);
    }

    public function test_unauthorized_reverse()
    {
        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->partner->id,
            'type' => 'capital_increase',
            'amount' => 1000.00,
            'entry_date' => now()->toIso8601String(),
        ];
        $response = $this->actingAs($this->admin)->postJson('/api/v1/owner-fund-transactions', $payload);
        $txId = $response->json('data.id');

        $unauthorizedUser = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
        ]);

        $this->actingAs($unauthorizedUser)->postJson("/api/v1/owner-fund-transactions/{$txId}/reverse")->assertStatus(403);
    }
}