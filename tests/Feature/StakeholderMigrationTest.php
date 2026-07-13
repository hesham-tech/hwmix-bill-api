<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Company;
use App\Models\User;
use App\Models\CompanyUser;
use Modules\Companies\Models\Branch;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceType;
use Modules\Accounting\Models\CashBox;
use Modules\Accounting\Models\CashBoxType;
use Modules\Companies\Models\BusinessRelation;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\DB;

/**
 * اختبار ميزانيات الأطراف بعد الترحيل والتحقق من صحة العلاقات والصناديق والأرصدة الدفترية.
 */
class StakeholderMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_successfully_migrates_relations_and_balances_and_archives_cashboxes()
    {
        // 1. إعداد الشركة والفرع والمستخدمين
        $company = Company::factory()->create(['name' => 'Test Company']);
        $branch = Branch::create([
            'name' => 'Main Branch',
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $adminUser = User::factory()->create(['full_name' => 'Admin User', 'nickname' => 'Admin']);
        $customerUser = User::factory()->create(['full_name' => 'Customer User', 'nickname' => 'Customer']);
        $supplierUser = User::factory()->create(['full_name' => 'Supplier User', 'nickname' => 'Supplier']);
        $employeeUser = User::factory()->create(['full_name' => 'Employee User', 'nickname' => 'Employee']);

        // ربط المستخدمين بالشركة
        CompanyUser::create([
            'user_id' => $adminUser->id,
            'company_id' => $company->id,
            'position_in_company' => 'Admin',
        ]);
        CompanyUser::create([
            'user_id' => $customerUser->id,
            'company_id' => $company->id,
        ]);
        CompanyUser::create([
            'user_id' => $supplierUser->id,
            'company_id' => $company->id,
        ]);
        CompanyUser::create([
            'user_id' => $employeeUser->id,
            'company_id' => $company->id,
            'position_in_company' => 'Sales Representative',
        ]);

        // إعطاء صلاحية Spatie للمشرف والموظف ليتعرف عليهما النظام كموظفين (أكبر من صفر صلاحية)
        DB::table('model_has_permissions')->insert([
            [
                'permission_id' => 1,
                'model_type' => User::class,
                'model_id' => $adminUser->id,
                'company_id' => $company->id,
            ],
            [
                'permission_id' => 2,
                'model_type' => User::class,
                'model_id' => $employeeUser->id,
                'company_id' => $company->id,
            ]
        ]);

        // 2. إعداد الصناديق القديمة
        $cashBoxType = CashBoxType::create([
            'name' => 'نقدي',
            'company_id' => $company->id,
        ]);

        $customerBox = CashBox::create([
            'name' => 'صندوق العميل',
            'balance' => 0.00,
            'cash_box_type_id' => $cashBoxType->id,
            'company_id' => $company->id,
            'user_id' => $customerUser->id,
            'created_by' => $adminUser->id,
            'is_active' => true,
        ]);

        $supplierBox = CashBox::create([
            'name' => 'صندوق المورد',
            'balance' => 0.00,
            'cash_box_type_id' => $cashBoxType->id,
            'company_id' => $company->id,
            'user_id' => $supplierUser->id,
            'created_by' => $adminUser->id,
            'is_active' => true,
        ]);

        $employeeBox = CashBox::create([
            'name' => 'صندوق الموظف',
            'balance' => 1500.00,
            'cash_box_type_id' => $cashBoxType->id,
            'company_id' => $company->id,
            'user_id' => $employeeUser->id,
            'created_by' => $adminUser->id,
            'is_active' => true,
        ]);

        // 3. إعداد أنواع الفواتير
        $saleType = InvoiceType::create([
            'name' => 'فاتورة بيع',
            'code' => 'sale',
            'context' => 'sales',
            'company_id' => $company->id,
            'created_by' => $adminUser->id,
        ]);

        $purchaseType = InvoiceType::create([
            'name' => 'فاتورة شراء',
            'code' => 'purchase',
            'context' => 'purchases',
            'company_id' => $company->id,
            'created_by' => $adminUser->id,
        ]);

        $saleReturnType = InvoiceType::create([
            'name' => 'مرتجع مبيعات',
            'code' => 'sale_return',
            'context' => 'sales',
            'company_id' => $company->id,
            'created_by' => $adminUser->id,
        ]);

        // 4. إنشاء الفواتير
        // فاتورة بيع للعميل بمبلغ 1000 تم سداد 300 منها (المتبقي: 700)
        Invoice::create([
            'invoice_number' => 'INV-SALE-01',
            'invoice_type_id' => $saleType->id,
            'invoice_type_code' => 'sale',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $customerUser->id,
            'gross_amount' => 1000.00,
            'net_amount' => 1000.00,
            'paid_amount' => 300.00,
            'status' => 'confirmed',
        ]);

        // فاتورة شراء من المورد بمبلغ 2000 تم سداد 500 منها (المتبقي: 1500)
        Invoice::create([
            'invoice_number' => 'INV-PURCHASE-01',
            'invoice_type_id' => $purchaseType->id,
            'invoice_type_code' => 'purchase',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $supplierUser->id,
            'gross_amount' => 2000.00,
            'net_amount' => 2000.00,
            'paid_amount' => 500.00,
            'status' => 'confirmed',
        ]);

        // مرتجع مبيعات للعميل بمبلغ 200 تم سداد 0 منها (يخصم 200 من رصيد العميل ليصبح المتبقي الكلي للعميل: 500)
        Invoice::create([
            'invoice_number' => 'INV-RETURN-01',
            'invoice_type_id' => $saleReturnType->id,
            'invoice_type_code' => 'sale_return',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $customerUser->id,
            'gross_amount' => 200.00,
            'net_amount' => 200.00,
            'paid_amount' => 0.00,
            'status' => 'confirmed',
        ]);

        // التأكد من أن جدول العلاقات والأرصدة فارغ قبل تشغيل الأمر
        $this->assertEquals(0, BusinessRelation::count());
        $this->assertEquals(0, StakeholderFinancialBalance::count());

        // 5. تشغيل أمر الترحيل المالي البرمجي
        $this->artisan('financial:migrate-balances', ['--fix' => true])
            ->assertExitCode(0);

        // 6. التحقق من تكوين العلاقات التجارية بشكل صحيح
        $this->assertDatabaseHas('business_relations', [
            'company_id' => $company->id,
            'user_id' => $customerUser->id,
            'relation_type' => 'customer',
        ]);

        $this->assertDatabaseHas('business_relations', [
            'company_id' => $company->id,
            'user_id' => $supplierUser->id,
            'relation_type' => 'supplier',
        ]);

        $this->assertDatabaseHas('business_relations', [
            'company_id' => $company->id,
            'user_id' => $employeeUser->id,
            'relation_type' => 'employee',
        ]);

        // 7. التحقق من ترحيل الأرصدة الدفترية بدقة
        // العميل: 1000 مبيعات - 300 مدفوع - 200 مرتجع = 500 مديونية (receivable)
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'company_id' => $company->id,
            'user_id' => $customerUser->id,
            'relation_type' => 'receivable',
            'balance' => 500.00,
        ]);

        // المورد: 2000 مشتريات - 500 مدفوع = 1500 مستحق له (payable)
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'company_id' => $company->id,
            'user_id' => $supplierUser->id,
            'relation_type' => 'payable',
            'balance' => 1500.00,
        ]);

        // 8. التحقق من أرشفة الصناديق القديمة للعملاء والموردين وسلامة خزن الموظفين
        $this->assertDatabaseHas('cash_boxes', [
            'id' => $customerBox->id,
            'status' => 'inactive',
            'access_type' => 'legacy_archived',
        ]);

        $this->assertDatabaseHas('cash_boxes', [
            'id' => $supplierBox->id,
            'status' => 'inactive',
            'access_type' => 'legacy_archived',
        ]);

        // صندوق الموظف يجب أن يظل نشطاً ومقترناً بالنوع الجديد
        $this->assertDatabaseHas('cash_boxes', [
            'id' => $employeeBox->id,
            'status' => 'active',
            'access_type' => 'user_owned',
        ]);

        // 9. تشغيل أمر التحقق والتأكد من نجاح الفحوصات والتدقيق المالي
        $this->artisan('financial:verify-migration')
            ->assertExitCode(0);
    }
}
