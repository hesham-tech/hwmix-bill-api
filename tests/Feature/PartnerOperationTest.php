<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\CashBoxType;
use App\Models\CashBox;
use App\Models\PartnerOperation;
use App\Models\Transaction;
use App\Models\FinancialLedger;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات موديول عمليات الشركاء (Partner Financial Operations) وكشوف الحسابات
 */
class PartnerOperationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $partner;
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

        $this->partner = User::factory()->create([
            'company_id' => $this->company->id,
            'nickname' => 'الشريك أحمد',
        ]);

        $this->boxType = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'نقدي',
        ]);

        $this->cashBox = CashBox::create([
            'name' => 'الخزينة الرئيسية للشركاء',
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->boxType->id,
            'balance' => 10000.00,
            'is_active' => true,
            'access_type' => 'company_shared',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * اختبار: الحصول على أنواع عمليات الشركاء
     */
    public function test_can_fetch_partner_operation_types(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/partner-operations/types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(6, 'data');
    }

    /**
     * اختبار: تسجيل زيادة رأس مال وتحديث الأثر المالي والخزنة
     */
    public function test_can_store_capital_increase_operation(): void
    {
        $payload = [
            'partner_id' => $this->partner->id,
            'cashbox_id' => $this->cashBox->id,
            'type' => 'capital_increase',
            'amount' => 5000.00,
            'operation_date' => now()->toIso8601String(),
            'notes' => 'زيادة رأس مال أولى',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/partner-operations', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 5000)
            ->assertJsonPath('data.type', 'capital_increase');

        // فحص تحديث رصيد الخزنة (10000 + 5000 = 15000)
        $this->assertEquals(15000.00, (float) $this->cashBox->fresh()->balance);

        // فحص إنشاء سجل Transaction (deposit)
        $this->assertDatabaseHas('transactions', [
            'cashbox_id' => $this->cashBox->id,
            'type' => 'deposit',
            'amount' => 5000.00,
        ]);

        // فحص قيود دفتر الأستاذ (Asset debit, Equity credit)
        $operationId = $response->json('data.id');
        $this->assertDatabaseHas('financial_ledger', [
            'source_type' => PartnerOperation::class,
            'source_id' => $operationId,
            'account_type' => 'asset',
            'type' => 'debit',
            'amount' => 5000.00,
        ]);

        $this->assertDatabaseHas('financial_ledger', [
            'source_type' => PartnerOperation::class,
            'source_id' => $operationId,
            'account_type' => 'equity',
            'type' => 'credit',
            'amount' => 5000.00,
        ]);
    }

    /**
     * اختبار: تسجيل سحب من رأس المال وتخفيض رصيد الخزنة
     */
    public function test_can_store_capital_withdrawal_operation(): void
    {
        $payload = [
            'partner_id' => $this->partner->id,
            'cashbox_id' => $this->cashBox->id,
            'type' => 'capital_withdrawal',
            'amount' => 2000.00,
            'notes' => 'سحب جزء من المساهمة',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/partner-operations', $payload);

        $response->assertStatus(201);

        // فحص خصم رصيد الخزنة (10000 - 2000 = 8000)
        $this->assertEquals(8000.00, (float) $this->cashBox->fresh()->balance);

        // فحص إنشاء سجل Transaction (withdraw)
        $this->assertDatabaseHas('transactions', [
            'cashbox_id' => $this->cashBox->id,
            'type' => 'withdraw',
            'amount' => 2000.00,
        ]);
    }

    /**
     * اختبار: سحب مبلغ أكبر من رصيد الخزنة يجب أن يفشل
     */
    public function test_withdrawal_exceeding_cashbox_balance_fails(): void
    {
        $payload = [
            'partner_id' => $this->partner->id,
            'cashbox_id' => $this->cashBox->id,
            'type' => 'capital_withdrawal',
            'amount' => 20000.00, // أكبر من 10000
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/partner-operations', $payload);

        $response->assertStatus(500);
        $this->assertEquals(10000.00, (float) $this->cashBox->fresh()->balance);
    }

    /**
     * اختبار: استخراج كشف حساب وصافي مساهمات الشريك
     */
    public function test_can_generate_partner_statement(): void
    {
        // 1. زيادة رأس مال: +5000
        $this->actingAs($this->admin)->postJson('/api/partner-operations', [
            'partner_id' => $this->partner->id,
            'cashbox_id' => $this->cashBox->id,
            'type' => 'capital_increase',
            'amount' => 5000.00,
        ]);

        // 2. تقديم قرض: +3000
        $this->actingAs($this->admin)->postJson('/api/partner-operations', [
            'partner_id' => $this->partner->id,
            'cashbox_id' => $this->cashBox->id,
            'type' => 'partner_loan_given',
            'amount' => 3000.00,
        ]);

        // 3. سداد قرض الشريك: -1000
        $this->actingAs($this->admin)->postJson('/api/partner-operations', [
            'partner_id' => $this->partner->id,
            'cashbox_id' => $this->cashBox->id,
            'type' => 'partner_loan_repaid',
            'amount' => 1000.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/partner-operations/statement/{$this->partner->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_deposits', 8000)
            ->assertJsonPath('data.summary.total_withdrawals', 1000)
            ->assertJsonPath('data.summary.net_balance', 7000)
            ->assertJsonPath('data.operations_count', 3);
    }
}
