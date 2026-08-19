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
use Modules\Accounting\Models\Custody;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Str;

class CustodyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
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
        $this->admin->givePermissionTo(perm_key('custodies.view_all'));
        $this->admin->givePermissionTo(perm_key('custodies.view_self'));
        $this->admin->givePermissionTo(perm_key('custodies.create'));
        $this->admin->givePermissionTo(perm_key('custodies.refund'));
        $this->admin->givePermissionTo(perm_key('custodies.reverse'));

        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'nickname' => 'Employee Ahmed',
        ]);

        $boxType = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Cash',
        ]);

        $this->cashBox = CashBox::create([
            'name' => 'Main Safe',
            'company_id' => $this->company->id,
            'cash_box_type_id' => $boxType->id,
            'balance' => 50000.00,
            'is_active' => true,
            'access_type' => 'company_shared',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_issue_custody()
    {
        $payload = [
            'user_id' => $this->employee->id,
            'cashbox_id' => $this->cashBox->id,
            'amount' => 10000.00,
            'issue_date' => now()->toDateString(),
            'description' => 'Travel expenses'
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/custodies', $payload);
        $response->assertStatus(201);
        
        $custodyId = $response->json('data.id');

        $this->assertDatabaseHas('custodies', [
            'id' => $custodyId,
            'amount' => 10000.00,
            'settled_cash_amount' => 0.00,
            'settled_expenses_amount' => 0.00,
            'status' => 'open'
        ]);

        // Check Financial Operation
        $op = FinancialOperation::where('source_type', Custody::class)
            ->where('source_id', $custodyId)->first();
        $this->assertNotNull($op);

        // Check Transaction (withdrawal from cashbox)
        $this->assertDatabaseHas('transactions', [
            'financial_operation_id' => $op->id,
            'type' => 'withdraw',
            'amount' => 10000.00,
            'cashbox_id' => $this->cashBox->id,
        ]);

        // Check Ledger
        $this->assertDatabaseHas('financial_ledger', [
            'account_type' => 'asset',
            'type' => 'debit',
            'amount' => 10000.00,
        ]);

        // Check Balance
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 10000.00,
        ]);
    }

    public function test_cash_refund()
    {
        $custody = Custody::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'cashbox_id' => $this->cashBox->id,
            'amount' => 10000.00,
            'settled_cash_amount' => 0,
            'settled_expenses_amount' => 0,
            'status' => 'open',
            'issue_date' => now(),
            'created_by' => $this->admin->id
        ]);

        StakeholderFinancialBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 10000.00,
        ]);

        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'amount' => 4000.00,
            'date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson("/api/v1/custodies/{$custody->id}/refund", $payload);
        $response->dump(); $response->assertStatus(200);

        $this->assertDatabaseHas('custodies', [
            'id' => $custody->id,
            'settled_cash_amount' => 4000.00,
            'status' => 'open'
        ]);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 6000.00,
        ]);
        
        $this->assertDatabaseHas('transactions', [
            'type' => 'deposit',
            'amount' => 4000.00,
            'cashbox_id' => $this->cashBox->id,
        ]);
    }

    public function test_expense_settlement()
    {
        $custody = Custody::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'cashbox_id' => $this->cashBox->id,
            'amount' => 10000.00,
            'settled_cash_amount' => 0,
            'settled_expenses_amount' => 0,
            'status' => 'open',
            'issue_date' => now(),
            'created_by' => $this->admin->id
        ]);
        
        StakeholderFinancialBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 10000.00,
        ]);

        $category = \Modules\Accounting\Models\ExpenseCategory::create(['company_id' => $this->company->id, 'name' => 'Food', 'created_by' => $this->admin->id]);

        $payload = [
            'expense_category_id' => $category->id,
            'amount' => 10000.00,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash', // Or custody maybe? Will handle in Expense controller
            'custody_id' => $custody->id,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/expenses', $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('custodies', [
            'id' => $custody->id,
            'settled_expenses_amount' => 10000.00,
            'status' => 'closed'
        ]);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 0.00,
        ]);
        
        $expenseId = $response->json('data.id');
        $op = FinancialOperation::where('source_type', \Modules\Accounting\Models\Expense::class)
            ->where('source_id', $expenseId)->first();
        
        $this->assertDatabaseMissing('transactions', [
            'financial_operation_id' => $op->id
        ]);
    }

    public function test_business_rules()
    {
        $custody = Custody::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'cashbox_id' => $this->cashBox->id,
            'amount' => 10000.00,
            'settled_cash_amount' => 0,
            'settled_expenses_amount' => 0,
            'status' => 'open',
            'issue_date' => now(),
            'created_by' => $this->admin->id
        ]);

        $payloadRefund = [
            'cashbox_id' => $this->cashBox->id,
            'amount' => 15000.00,
            'date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson("/api/v1/custodies/{$custody->id}/refund", $payloadRefund);
        $response->assertStatus(422); 
        
        $category = \Modules\Accounting\Models\ExpenseCategory::create(['company_id' => $this->company->id, 'name' => 'Food', 'created_by' => $this->admin->id]);
        
        $payloadExpense = [
            'expense_category_id' => $category->id,
            'amount' => 15000.00,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'custody_id' => $custody->id,
        ];

        $response2 = $this->actingAs($this->admin)->postJson('/api/v1/expenses', $payloadExpense);
        $response2->assertStatus(422); 
    }

    public function test_can_reverse_custody_issue()
    {
        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'user_id' => $this->employee->id,
            'amount' => 10000.00,
            'issue_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/custodies', $payload);
        $custodyId = $response->json('data.id');

        $operation = \App\Models\FinancialOperation::where('source_type', \Modules\Accounting\Models\Custody::class)->where('source_id', $custodyId)->first();

        // reverse via API
        $this->actingAs($this->admin)->postJson("/api/v1/custodies/{$custodyId}/reverse")->assertStatus(200);

        $this->assertDatabaseHas('custodies', [
            'id' => $custodyId,
            'status' => 'canceled'
        ]);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 0.00,
        ]);
    }

    public function test_can_reverse_custody_refund()
    {
        $custody = \Modules\Accounting\Models\Custody::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'cashbox_id' => $this->cashBox->id,
            'amount' => 10000.00,
            'settled_cash_amount' => 0.00,
            'status' => 'open',
            'issue_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        \Modules\Companies\Models\StakeholderFinancialBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 10000.00,
        ]);

        $payload = [
            'cashbox_id' => $this->cashBox->id,
            'amount' => 4000.00,
            'date' => now()->toDateString(),
        ];

        $this->actingAs($this->admin)->postJson("/api/v1/custodies/{$custody->id}/refund", $payload);

        $operation = \App\Models\FinancialOperation::where('source_type', \Modules\Accounting\Models\Custody::class)->where('source_id', $custody->id)->where('type', 'custody_refund')->first();
        
        $engine = app(\App\Services\FinancialEngine::class);
        $engine->reverseOperation($operation->id);

        $this->assertDatabaseHas('custodies', [
            'id' => $custody->id,
            'settled_cash_amount' => 0.00,
        ]);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 10000.00,
        ]);
    }

    public function test_can_reverse_custody_expense()
    {
        $custody = \Modules\Accounting\Models\Custody::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'cashbox_id' => $this->cashBox->id,
            'amount' => 10000.00,
            'settled_expenses_amount' => 0.00,
            'status' => 'open',
            'issue_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        \Modules\Companies\Models\StakeholderFinancialBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 10000.00,
        ]);

        $category = \Modules\Accounting\Models\ExpenseCategory::create(['company_id' => $this->company->id, 'name' => 'Food', 'created_by' => $this->admin->id]);

        $payload = [
            'expense_category_id' => $category->id,
            'amount' => 10000.00,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'custody_id' => $custody->id,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/expenses', $payload);
        $expenseId = $response->json('data.id');

        $operation = \App\Models\FinancialOperation::where('source_type', \Modules\Accounting\Models\Expense::class)->where('source_id', $expenseId)->first();

        $engine = app(\App\Services\FinancialEngine::class);
        $engine->reverseOperation($operation->id);

        $this->assertDatabaseHas('custodies', [
            'id' => $custody->id,
            'settled_expenses_amount' => 0.00,
        ]);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $this->employee->id,
            'relation_type' => 'custody',
            'balance' => 10000.00,
        ]);
    }
}
