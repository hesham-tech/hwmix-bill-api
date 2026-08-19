<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Modules\Accounting\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\FinancialOperation;
use App\Contracts\FinancialEngineInterface;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

/**
 * اختبارات البنية التأسيسية للمحرك المالي وتكامل الحركات والعمليات المالية الصارمة.
 */
class FinancialEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected CashBox $cashBox;
    protected CashBoxType $boxType;
    protected FinancialEngineInterface $engine;

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

        $this->engine = app(FinancialEngineInterface::class);
    }

    /**
     * اختبار: إيداع نقدية واستلام أموال بالخزينة
     */
    public function test_receive_money_updates_cashbox_balance_and_creates_transaction()
    {
        $operationId = (string) Str::uuid();
        
        // إنشاء العملية المالية أولاً لتلبية invariant الربط الهيكلي
        FinancialOperation::create([
            'id' => $operationId,
            'company_id' => $this->company->id,
            'type' => 'payment_receipt',
            'status' => 'active',
            'amount' => 500.00,
            'created_by' => $this->admin->id,
        ]);

        $this->engine->receiveMoney(500.00, $this->cashBox->id, $operationId);

        // التحقق من تعديل الرصيد
        $this->assertEquals(1500.00, $this->cashBox->fresh()->balance);

        // التحقق من تسجيل الحركة وربطها الهيكلي بالعملية
        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->company->id,
            'cashbox_id' => $this->cashBox->id,
            'amount' => 500.00,
            'type' => 'deposit',
            'financial_operation_id' => $operationId
        ]);
    }

    /**
     * اختبار: صرف نقدية من الخزينة
     */
    public function test_pay_money_updates_cashbox_balance_and_creates_transaction()
    {
        $operationId = (string) Str::uuid();
        
        FinancialOperation::create([
            'id' => $operationId,
            'company_id' => $this->company->id,
            'type' => 'expense_creation',
            'status' => 'active',
            'amount' => 300.00,
            'created_by' => $this->admin->id,
        ]);

        $this->engine->payMoney(300.00, $this->cashBox->id, $operationId);

        $this->assertEquals(700.00, $this->cashBox->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->company->id,
            'cashbox_id' => $this->cashBox->id,
            'amount' => 300.00,
            'type' => 'withdraw',
            'financial_operation_id' => $operationId
        ]);
    }

    /**
     * اختبار: إثبات وتخفيض مديونية العميل
     */
    public function test_create_and_reduce_receivable_balances()
    {
        $customer = User::factory()->create(['company_id' => $this->company->id]);
        $operationId = (string) Str::uuid();

        FinancialOperation::create([
            'id' => $operationId,
            'company_id' => $this->company->id,
            'type' => 'invoice_sale_creation',
            'status' => 'active',
            'amount' => 1200.00,
            'created_by' => $this->admin->id,
        ]);

        // إثبات المديونية
        $this->engine->createReceivable($customer, 1200.00, $operationId);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'company_id' => $this->company->id,
            'user_id' => $customer->id,
            'relation_type' => 'receivable',
            'balance' => 1200.00
        ]);

        // تحصيل وتخفيض المديونية
        $this->engine->reduceReceivable($customer, 400.00, $operationId);

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'company_id' => $this->company->id,
            'user_id' => $customer->id,
            'relation_type' => 'receivable',
            'balance' => 800.00
        ]);
    }

    /**
     * اختبار: تحويل نقدية بين خزنتين مع القفل
     */
    public function test_transfer_cash_between_cashboxes()
    {
        $targetBox = CashBox::create([
            'name' => 'الخزينة الفرعية',
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->boxType->id,
            'balance' => 200.00,
            'is_active' => true,
            'access_type' => 'company_shared',
            'created_by' => $this->admin->id,
        ]);

        $operationId = (string) Str::uuid();

        // Remove pre-creation of operation because transferCash will create it and use it.

        $this->engine->transferCash($this->cashBox->id, $targetBox->id, 400.00, $operationId);

        $this->assertEquals(600.00, $this->cashBox->fresh()->balance);
        $this->assertEquals(600.00, $targetBox->fresh()->balance);
    }

    /**
     * اختبار: إلغاء وعكس العملية المالية بالكامل (Reverse Operation)
     */
    public function test_reverse_operation_reverses_all_child_entries_and_balances()
    {
        $customer = User::factory()->create(['company_id' => $this->company->id]);
        $operationId = (string) Str::uuid();

        // 1. إنشاء العملية التأسيسية
        FinancialOperation::create([
            'id' => $operationId,
            'company_id' => $this->company->id,
            'type' => 'invoice_sale_creation',
            'status' => 'active',
            'amount' => 1000.00,
            'created_by' => $this->admin->id,
        ]);

        // 2. تطبيق الآثار التابعة للعملية
        $this->engine->createReceivable($customer, 1000.00, $operationId);
        $this->engine->receiveMoney(300.00, $this->cashBox->id, $operationId);
        $this->engine->reduceReceivable($customer, 300.00, $operationId);

        $this->assertEquals(1300.00, $this->cashBox->fresh()->balance);

        // 3. استدعاء العكس الكلي
        $reversalOpId = $this->engine->reverseOperation($operationId);

        // 4. التحقق من إلغاء وتصفير الأثر المالي
        $this->assertEquals('reversed', FinancialOperation::find($operationId)->status);
        $this->assertEquals(1000.00, $this->cashBox->fresh()->balance); // تراجع الـ 300 ج

        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'company_id' => $this->company->id,
            'user_id' => $customer->id,
            'relation_type' => 'receivable',
            'balance' => 0.00
        ]);

        // التحقق من ترحيل قيود عكسية لتوثيق الإلغاء
        $this->assertDatabaseHas('financial_operations', [
            'id' => $reversalOpId,
            'type' => 'invoice_sale_creation_reversal',
            'status' => 'active',
            'amount' => 1000.00
        ]);
    }

    /**
     * اختبار: Idempotency يمنع تكرار معالجة الطلب المالي
     */
    public function test_idempotency_prevents_duplicate_processing()
    {
        $operationId = (string) Str::uuid();

        $operation = FinancialOperation::create([
            'id' => $operationId,
            'company_id' => $this->company->id,
            'type' => 'payment_receipt',
            'status' => 'active',
            'amount' => 200.00,
            'created_by' => $this->admin->id,
        ]);

        // تنفيذ أول مرة
        $this->engine->receiveMoney(200.00, $this->cashBox->id, $operationId);
        $this->assertEquals(1200.00, $this->cashBox->fresh()->balance);

        // محاولة تنفيذ ثاني مرة بنفس الـ ID
        $this->engine->receiveMoney(200.00, $this->cashBox->id, $operationId);

        // يجب ألا يتغير الرصيد نهائياً لضمان الـ Idempotency
        $this->assertEquals(1200.00, $this->cashBox->fresh()->balance);
    }

    /**
     * اختبار: القوانين غير القابلة للكسر (Invariants Layer)
     */
    public function test_invariants_prevent_negative_values()
    {
        $this->expectException(\Exception::class);

        $operationId = (string) Str::uuid();
        FinancialOperation::create([
            'id' => $operationId,
            'company_id' => $this->company->id,
            'type' => 'expense_creation',
            'status' => 'active',
            'amount' => 1500.00, // أكبر من الرصيد المتوفر (1000.00)
            'created_by' => $this->admin->id,
        ]);

        // محاولة سحب مبلغ أكبر من الرصيد بالخزنة مما يتسبب في كسر invariant الرصيد الإيجابي
        $this->engine->payMoney(1500.00, $this->cashBox->id, $operationId);
    }
}
