<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\Transaction;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceType;
use Modules\Companies\Models\Branch;
use Modules\Companies\Models\BusinessRelation;
use Modules\Accounting\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class E2EAccountingScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $branch;
    protected $staff;
    protected $customer;
    protected $supplier;
    protected $cashBoxType;
    protected $staffCashBox;
    protected $sharedCashBox;
    protected $invoiceTypeSale;
    protected $invoiceTypePurchase;
    protected $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions
        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);

        // 1. Create Company and Branch
        $this->company = Company::factory()->create(['name' => 'شركة E2E']);
        $this->branch = Branch::create([
            'name' => 'الفرع الرئيسي E2E',
            'company_id' => $this->company->id,
        ]);

        // 2. Create Users
        $this->staff = User::factory()->create([
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = User::factory()->create([
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->supplier = User::factory()->create([
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        // 3. Link Users to Company via CompanyUser
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

        CompanyUser::create([
            'user_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'status' => 'active',
            'created_by' => $this->staff->id,
        ]);

        // 4. Create Business Relations (Stakeholder Roles)
        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'relation_type' => 'employee',
            'created_by' => $this->staff->id,
        ]);

        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->customer->id,
            'relation_type' => 'customer',
            'created_by' => $this->staff->id,
        ]);

        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->supplier->id,
            'relation_type' => 'supplier',
            'created_by' => $this->staff->id,
        ]);

        // 5. Setup Spatie Permission Scope
        setPermissionsTeamId($this->company->id);
        $this->staff->givePermissionTo('admin.super');

        // 6. Create Cash Box Types and Cash Boxes
        $this->cashBoxType = CashBoxType::create([
            'name' => 'نقدي E2E',
            'created_by' => $this->staff->id,
        ]);

        // Staff cashbox
        $this->staffCashBox = CashBox::create([
            'name' => 'خزنة المحاسب الشخصية',
            'user_id' => $this->staff->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'cash_box_type_id' => $this->cashBoxType->id,
            'balance' => 10000,
            'is_default' => true,
            'created_by' => $this->staff->id,
        ]);

        // Company shared cashbox
        $this->sharedCashBox = CashBox::create([
            'name' => 'خزنة الفرع المشتركة',
            'user_id' => null,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'cash_box_type_id' => $this->cashBoxType->id,
            'access_type' => 'company_shared',
            'balance' => 5000,
            'is_default' => false,
            'created_by' => $this->staff->id,
        ]);

        // 7. Create Invoice Types
        $this->invoiceTypeSale = InvoiceType::create([
            'code' => 'sale',
            'name' => 'فاتورة مبيعات E2E',
            'company_id' => $this->company->id,
            'created_by' => $this->staff->id,
        ]);

        $this->invoiceTypePurchase = InvoiceType::create([
            'code' => 'purchase',
            'name' => 'فاتورة مشتريات E2E',
            'company_id' => $this->company->id,
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);
        $this->accountingService = app(AccountingService::class);
    }

    /**
     * Run the complete ERP end-to-end accounting flow and verify health check.
     */
    public function test_e2e_accounting_and_audit_scenario()
    {
        // === خطوة 1: فاتورة شراء من المورد بقيمة 5000 وسداد 2000 ===
        $purchaseInvoice = Invoice::create([
            'invoice_number' => 'INV-PURCHASE-E2E',
            'gross_amount' => 5000,
            'net_amount' => 5000,
            'paid_amount' => 2000,
            'remaining_amount' => 3000,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->supplier->id,
            'invoice_type_id' => $this->invoiceTypePurchase->id,
            'invoice_type_code' => 'purchase',
        ]);

        $this->accountingService->recordInvoiceCreation($purchaseInvoice, [
            'cash_box_id' => $this->staffCashBox->id,
        ]);

        // التحقق من الأرصدة
        // رصيد المورد التراكمي (ذمة دائنة) = 3000
        $this->assertEquals(3000, $this->supplier->fresh()->getFinancialBalance($this->company->id, 'payable'));
        // رصيد خزنة الموظف = 10000 - 2000 = 8000
        $this->assertEquals(8000, $this->staffCashBox->fresh()->balance);

        // === خطوة 2: فاتورة مبيعات للعميل بقيمة 3000 مع قبض 1000 ===
        $saleInvoice = Invoice::create([
            'invoice_number' => 'INV-SALE-E2E',
            'gross_amount' => 3000,
            'net_amount' => 3000,
            'paid_amount' => 1000,
            'remaining_amount' => 2000,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'user_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceTypeSale->id,
            'invoice_type_code' => 'sale',
        ]);

        $this->accountingService->recordInvoiceCreation($saleInvoice, [
            'cash_box_id' => $this->staffCashBox->id,
        ]);

        // التحقق من الأرصدة
        // رصيد العميل التراكمي (ذمة مدينة) = 2000
        $this->assertEquals(2000, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        // رصيد خزنة الموظف = 8000 + 1000 = 9000
        $this->assertEquals(9000, $this->staffCashBox->fresh()->balance);

        // === خطوة 3: تحصيل دفعة إضافية من العميل بقيمة 500 ===
        $this->accountingService->collectPayment($this->staff, $this->customer, 500, [
            'cash_box_id' => $this->staffCashBox->id,
        ]);

        // رصيد العميل التراكمي يصبح 1500 (2000 - 500)
        $this->assertEquals(1500, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        // رصيد خزنة الموظف يصبح 9500 (9000 + 500)
        $this->assertEquals(9500, $this->staffCashBox->fresh()->balance);

        // === خطوة 4: إلغاء فاتورة المبيعات بالكامل وعكس أثر النقدية والدين ===
        $saleInvoice->status = 'canceled';
        $saleInvoice->save();

        $this->accountingService->reverseInvoice($saleInvoice, [
            'cash_box_id' => $this->staffCashBox->id,
        ]);

        // رصيد العميل بعد الإلغاء يعود ليكون -500 (لأنه تم تحصيل 500 بدون مديونية فاتورة قائمة)
        $this->assertEquals(-500, $this->customer->fresh()->getFinancialBalance($this->company->id, 'receivable'));
        // رصيد خزنة الموظف يصبح 8500 (9500 - 1000 مستردة)
        $this->assertEquals(8500, $this->staffCashBox->fresh()->balance);

        // === خطوة 5: تشغيل أداة التدقيق المالي ومراقبة السلامة العامة للبيانات ===
        $this->assertEquals(0, \Illuminate\Support\Facades\Artisan::call('financial:audit-balances'));
    }
}
