<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\InvoiceType;
use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\CompanyUser;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoicePayment;
use Modules\Accounting\Services\AccountingService;
use Modules\Accounting\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class AccountingRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected $staff;
    protected $customer;
    protected $company;
    protected $branch;
    protected $cashBoxType;
    protected $staffCashBox;
    protected $customerCashBox;
    protected $invoiceTypeSale;
    protected $invoiceTypePurchase;
    protected $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions from config if needed
        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);

        // Create core models
        $this->company = Company::factory()->create();
        $this->branch = \Modules\Companies\Models\Branch::create([
            'company_id' => $this->company->id,
            'name' => 'الفرع الرئيسي',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->staff = User::factory()->create([
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = User::factory()->create([
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        // Link users to company
        CompanyUser::create([
            'user_id' => $this->staff->id,
            'company_id' => $this->company->id,
            'status' => 'active',
            'created_by' => $this->staff->id,
        ]);

        CompanyUser::create([
            'user_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'status' => 'active',
            'created_by' => $this->staff->id,
        ]);

        \Modules\Companies\Models\BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'relation_type' => 'employee',
            'created_by' => $this->staff->id,
        ]);

        \Modules\Companies\Models\BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->customer->id,
            'relation_type' => 'customer',
            'created_by' => $this->staff->id,
        ]);

        // Setup Spatie permissions scoping
        setPermissionsTeamId($this->company->id);
        $this->staff->givePermissionTo('admin.super');

        // Create Cash Box Types and Cash Boxes
        $this->cashBoxType = CashBoxType::factory()->create(['name' => 'نقدي']);

        $this->staffCashBox = CashBox::factory()->create([
            'name' => 'خزنة الموظف',
            'user_id' => $this->staff->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'cash_box_type_id' => $this->cashBoxType->id,
            'balance' => 0,
            'is_default' => true,
        ]);

        $this->customerCashBox = CashBox::factory()->create([
            'name' => 'خزنة العميل',
            'user_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'cash_box_type_id' => $this->cashBoxType->id,
            'balance' => 0,
            'is_default' => true,
        ]);

        // Create Invoice Types
        $this->invoiceTypeSale = InvoiceType::factory()->create([
            'code' => 'sale',
            'name' => 'فاتورة مبيعات',
            'company_id' => $this->company->id,
        ]);

        $this->invoiceTypePurchase = InvoiceType::factory()->create([
            'code' => 'purchase',
            'name' => 'فاتورة مشتريات',
            'company_id' => $this->company->id,
        ]);

        // Set Auth User
        $this->actingAs($this->staff);

        $this->accountingService = app(AccountingService::class);
    }

    /**
     * 1. Test that creating a sale invoice updates cash box balances correctly.
     */
    public function test_invoice_creation_updates_cashbox_balance()
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SALE-101',
            'gross_amount' => 1500,
            'total_discount' => 0,
            'net_amount' => 1500,
            'paid_amount' => 500,
            'remaining_amount' => 1000,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Customer balance should represent the net debt (net_amount - paid_amount = 1500 - 500 = 1000)
        $this->assertEquals(1000, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));

        // Staff cashbox balance should increase by paid_amount (0 + 500 = 500)
        $this->assertEquals(500, $this->staffCashBox->fresh()->balance);
    }

    /**
     * 2. Test that creating a sale invoice for a default cash customer does not affect the customer's balance.
     */
    public function test_invoice_creation_for_cash_customer_does_not_update_party_balance()
    {
        // Set customer as the default cash customer for the company
        $this->company->default_cash_customer_id = $this->customer->id;
        $this->company->save();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-SALE-CASH',
            'gross_amount' => 1500,
            'total_discount' => 0,
            'net_amount' => 1500,
            'paid_amount' => 1500,
            'remaining_amount' => 0,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Cash customer's balance should remain 0 (it is skipped)
        $this->assertEquals(0, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));

        // Staff cashbox balance increases by paid amount (0 + 1500 = 1500)
        $this->assertEquals(1500, $this->staffCashBox->fresh()->balance);
    }

    /**
     * 3. Test that canceling an invoice reverses all changes.
     */
    public function test_invoice_cancellation_reverses_cashbox_balance()
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SALE-REV',
            'gross_amount' => 1000,
            'total_discount' => 0,
            'net_amount' => 1000,
            'paid_amount' => 400,
            'remaining_amount' => 600,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        $this->assertEquals(600, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        $this->assertEquals(400, $this->staffCashBox->fresh()->balance);

        // Cancel the invoice
        $this->accountingService->reverseInvoice($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Balances should be back to original
        $this->assertEquals(0, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        $this->assertEquals(0, $this->staffCashBox->fresh()->balance);
    }

    /**
     * 4. Test that installment payment reduces the client's balance.
     */
    public function test_installment_payment_reduces_cashbox_balance()
    {
        // First create a debt of 1000
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SALE-INST',
            'gross_amount' => 1000,
            'total_discount' => 0,
            'net_amount' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 1000,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        $this->assertEquals(1000, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        $this->assertEquals(0, $this->staffCashBox->fresh()->balance);

        // Receive payment of 300
        $this->accountingService->collectPayment($this->staff, $this->customer, 300, [
            'cash_box_id' => $this->staffCashBox->id,
            'party_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Customer balance should decrease by 300 (debt drops to 700)
        $this->assertEquals(700, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));

        // Staff cashbox balance should increase by 300 (0 + 300 = 300)
        $this->assertEquals(300, $this->staffCashBox->fresh()->balance);
    }

    /**
     * 5. Test multi-tenant isolation.
     */
    public function test_multi_tenant_isolation()
    {
        $anotherCompany = Company::factory()->create();

        // Querying with CompanyScope active should return only this company's cash boxes
        $boxCompany1 = CashBox::factory()->create([
            'name' => 'خزنة شركة 1',
            'user_id' => $this->staff->id,
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->cashBoxType->id,
        ]);

        $boxCompany2 = CashBox::factory()->create([
            'name' => 'خزنة شركة 2',
            'user_id' => $this->staff->id,
            'company_id' => $anotherCompany->id,
            'cash_box_type_id' => $this->cashBoxType->id,
        ]);

        // In active scope of company 1
        $boxes = CashBox::all();
        $this->assertTrue($boxes->contains($boxCompany1));
        $this->assertFalse($boxes->contains($boxCompany2));
    }

    /**
     * 6. Test cashbox balance matches sum of transactions.
     */
    public function test_cashbox_balance_matches_transaction_sum()
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SALE-SUM',
            'gross_amount' => 1000,
            'net_amount' => 1000,
            'paid_amount' => 200,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Let's do some more transactions
        $this->accountingService->collectPayment($this->staff, $this->customer, 150, [
            'cash_box_id' => $this->staffCashBox->id,
            'party_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Check staff cashbox balance matches transactions sum
        // Initial was 0. Transactions: +200, +150
        $staffBalance = $this->staffCashBox->fresh()->balance;
        $this->assertEquals(350, $staffBalance);

        // Sum of transactions for this cashbox
        $transactionsSum = Transaction::where('cashbox_id', $this->staffCashBox->id)
            ->get()
            ->sum(function ($t) {
                return in_array($t->type, ['deposit', 'transfer_in', 'reverse_withdraw']) ? $t->amount : -$t->amount;
            });

        // The staff cashbox balance should match initial + transactions sum
        $this->assertEquals(350, $transactionsSum);
    }

    /**
     * 7. Test that transactions are correctly linked to invoices via source_invoice_id.
     */
    public function test_transactions_are_linked_to_invoices()
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SALE-LINK',
            'gross_amount' => 1000,
            'net_amount' => 1000,
            'paid_amount' => 300,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Get all transactions created for this invoice
        $txs = Transaction::where('source_invoice_id', $invoice->id)->get();

        // There should be at least two transactions (deposit of debt on customer, deposit of payment on staff)
        $this->assertGreaterThanOrEqual(2, $txs->count());

        foreach ($txs as $t) {
            $this->assertEquals($invoice->id, $t->source_invoice_id);
        }
    }

    /**
     * 8. Test that a user can have multiple business relations (backward compatibility & expansion).
     */
    public function test_user_can_have_multiple_business_relations()
    {
        // Link customer user to company as employee as well via BusinessRelation
        \Modules\Companies\Models\BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->customer->id,
            'relation_type' => 'employee',
            'created_by' => $this->staff->id,
        ]);

        // Assert customer has both 'customer' (via CompanyUser sync) and 'employee' (manually created)
        $relations = \Modules\Companies\Models\BusinessRelation::where([
            'company_id' => $this->company->id,
            'user_id' => $this->customer->id,
        ])->get();

        $this->assertEquals(2, $relations->count());
        $this->assertTrue($relations->contains('relation_type', 'customer'));
        $this->assertTrue($relations->contains('relation_type', 'employee'));
    }

    /**
     * 9. Test that a shared cashbox can exist without user_id and is correctly resolved when user has no personal cashbox.
     */
    public function test_shared_cashbox_resolution()
    {
        // Create a new staff user who has NO cash box at all
        $newStaff = User::factory()->create([
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        CompanyUser::create([
            'user_id' => $newStaff->id,
            'company_id' => $this->company->id,
            'status' => 'active',
            'created_by' => $this->staff->id,
        ]);

        // Note: CompanyUserObserver creates a default personal cashbox by default.
        // Let's delete it so this user has NO personal cashbox.
        CashBox::withoutGlobalScopes()->where('user_id', $newStaff->id)->delete();

        // Verify the user has no personal cashbox now
        $this->assertEquals(0, CashBox::withoutGlobalScopes()->where('user_id', $newStaff->id)->count());

        // Create a company shared cashbox (user_id is NULL)
        $sharedBox = CashBox::factory()->create([
            'name' => 'خزنة الشركة المشتركة',
            'user_id' => null,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'cash_box_type_id' => $this->cashBoxType->id,
            'access_type' => 'company_shared',
            'balance' => 5000,
            'is_default' => false,
        ]);

        // Set authenticated user to the new staff
        $this->actingAs($newStaff);

        // Create an invoice
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SALE-SHARED',
            'gross_amount' => 1000,
            'net_amount' => 1000,
            'paid_amount' => 400,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $newStaff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        // Record invoice creation. It should fallback to the shared company cashbox for the staff payment.
        $this->accountingService->recordInvoiceCreation($invoice, [
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Shared cashbox balance should increase by paid_amount (5000 + 400 = 5400)
        $this->assertEquals(5400, $sharedBox->fresh()->balance);

        $tx = Transaction::where('cashbox_id', $sharedBox->id)->latest('id')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(400, $tx->amount);
        $this->assertEquals($newStaff->id, $tx->user_id);
    }

    /**
     * 10. Test that stakeholder financial balances are calculated and migrated correctly by the console command.
     */
    public function test_stakeholder_financial_balance_calculation()
    {
        // Clear existing balances if any
        \Modules\Companies\Models\StakeholderFinancialBalance::truncate();

        // Create an invoice with remaining amount
        Invoice::create([
            'invoice_number' => 'INV-MIG-1',
            'gross_amount' => 1000,
            'net_amount' => 1000,
            'paid_amount' => 400,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        // Run the balance migration command
        $this->artisan('financial:migrate-balances', ['--fix' => true]);

        // Assert that a receivable balance of 600 (1000 - 400) was created for the customer
        $balanceEntry = \Modules\Companies\Models\StakeholderFinancialBalance::where([
            'company_id' => $this->company->id,
            'user_id' => $this->customer->id,
            'relation_type' => 'receivable',
        ])->first();

        $this->assertNotNull($balanceEntry);
        $this->assertEquals(600, (float)$balanceEntry->balance);
    }

    /**
     * 11. Comprehensive financial audit and data integrity test.
     * Verifies that after creation, partial payment, cancellation, reversal, and collection:
     * - The Stakeholder Financial Balance matches the calculated ledger.
     * - The CashBox balance matches the cash box transactions sum.
     * - The Transactions table contains all correct entries.
     */
    public function test_financial_audit_data_integrity()
    {
        // 1. Create a sale invoice of 1000 with a partial payment of 300
        $invoice = Invoice::create([
            'invoice_number' => 'INV-AUDIT-1',
            'gross_amount' => 1000,
            'net_amount' => 1000,
            'paid_amount' => 300,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Assert balances
        $this->assertEquals(700, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        $this->assertEquals(300, $this->staffCashBox->fresh()->balance);

        // 2. Cancel the invoice (reverses both cash payment and receivable debt)
        $this->accountingService->reverseInvoice($invoice, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Assert balances are back to 0
        $this->assertEquals(0, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        $this->assertEquals(0, $this->staffCashBox->fresh()->balance);

        // 3. Create a new sale invoice of 1500 with a partial payment of 500
        $invoice2 = Invoice::create([
            'invoice_number' => 'INV-AUDIT-2',
            'gross_amount' => 1500,
            'net_amount' => 1500,
            'paid_amount' => 500,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($invoice2, [
            'cash_box_id' => $this->staffCashBox->id,
            'user_cash_box_id' => $this->customerCashBox->id,
        ]);

        $this->assertEquals(1000, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        $this->assertEquals(500, $this->staffCashBox->fresh()->balance);

        // 4. Collect another 300 payment from the customer
        $this->accountingService->collectPayment($this->staff, $this->customer, 300, [
            'cash_box_id' => $this->staffCashBox->id,
            'party_cash_box_id' => $this->customerCashBox->id,
        ]);

        // Balances:
        // Customer receivable should be 1000 - 300 = 700
        // Staff cashbox should be 500 + 300 = 800
        $this->assertEquals(700, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        $this->assertEquals(800, $this->staffCashBox->fresh()->balance);

        // 5. Verify transactions integrity for the customer (receivable account)
        // All transactions for the customer where cashbox_id is null
        $partyTransactions = Transaction::where('user_id', $this->customer->id)
            ->whereNull('cashbox_id')
            ->orderBy('id', 'asc')
            ->get();

        // Let's trace their effects on the receivable balance:
        // Initial = 0
        // +1000 (deposit - invoice 1)
        // -1000 (withdraw - cancel invoice 1)
        // +1500 (deposit - invoice 2)
        // -500 (withdraw - invoice 2 payment)
        // -300 (withdraw - collect payment)
        // Expected net = 700
        $calculatedPartyBalance = $partyTransactions->reduce(function ($carry, $t) {
            return in_array($t->type, ['deposit', 'transfer_in', 'reverse_withdraw']) 
                ? $carry + (float)$t->amount 
                : $carry - (float)$t->amount;
        }, 0.00);

        $this->assertEquals(700, $calculatedPartyBalance);

        // 6. Verify transactions integrity for the staff cashbox
        $staffTransactions = Transaction::where('cashbox_id', $this->staffCashBox->id)
            ->orderBy('id', 'asc')
            ->get();

        // Trace cashbox balance:
        // Initial = 0
        // +300 (deposit - invoice 1)
        // -300 (withdraw - cancel invoice 1)
        // +500 (deposit - invoice 2)
        // +300 (deposit - collect payment)
        // Expected net = 800
        $calculatedStaffBalance = $staffTransactions->reduce(function ($carry, $t) {
            return in_array($t->type, ['deposit', 'transfer_in', 'reverse_withdraw']) 
                ? $carry + (float)$t->amount 
                : $carry - (float)$t->amount;
        }, 0.00);

        $this->assertEquals(800, $calculatedStaffBalance);
        $this->assertEquals(800, (float)$this->staffCashBox->fresh()->balance);
    }
}
