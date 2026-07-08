<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\InstallmentPayment;
use App\Models\PaymentMethod;
use App\Models\CashBox;
use Modules\Sales\Models\Invoice;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات متحكم دفع الأقساط (InstallmentPaymentController).
 */
class InstallmentPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected User $customer;
    protected InstallmentPlan $plan;
    protected Invoice $invoice;
    protected CashBox $cashBox;
    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        // إنشاء العلاقات للمشرف ليكون موظفاً ويملك عهدة
        $employeeRelationType = \Modules\Companies\Models\RelationType::firstOrCreate(
            ['code' => 'employee'],
            ['display_name' => 'موظف']
        );
        $employeeCap = \Modules\Companies\Models\Capability::firstOrCreate(
            ['code' => 'has_cash_custody'],
            ['display_name' => 'امتلاك عهدة مالية']
        );
        $employeeRelationType->capabilities()->syncWithoutDetaching([$employeeCap->id]);

        \Modules\Companies\Models\BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'relation_type' => 'employee',
            'relation_type_id' => $employeeRelationType->id,
            'is_active' => true,
        ]);

        $this->cashBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->paymentMethod = PaymentMethod::factory()->create([
            'company_id' => $this->company->id,
            'active' => true,
        ]);

        $this->customer = User::factory()->create(['company_id' => $this->company->id]);
        $this->invoice = Invoice::factory()->create(['company_id' => $this->company->id]);
        $this->plan = InstallmentPlan::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);
    }

    /** @test */
    public function test_can_list_installment_payments()
    {
        $this->actingAs($this->admin);

        InstallmentPayment::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'installment_plan_id' => $this->plan->id,
        ]);

        $response = $this->getJson('/api/v1/installment-payments');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_pay_installments()
    {
        $this->actingAs($this->admin);

        $installment = Installment::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'installment_plan_id' => $this->plan->id,
            'amount' => 500,
            'remaining' => 500,
            'status' => 'pending',
        ]);

        $payload = [
            'installment_plan_id' => $this->plan->id,
            'installment_ids' => [$installment->id],
            'amount' => 500,
            'payment_method_id' => $this->paymentMethod->id,
            'cash_box_id' => $this->cashBox->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'سداد القسط الأول',
        ];

        $response = $this->postJson('/api/v1/installment-payments', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('installments', [
            'id' => $installment->id,
            'status' => 'paid',
            'remaining' => 0.00
        ]);
    }

    /** @test */
    public function test_can_show_installment_payment()
    {
        $this->actingAs($this->admin);

        $payment = InstallmentPayment::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'installment_plan_id' => $this->plan->id,
        ]);

        $response = $this->getJson("/api/v1/installment-payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $payment->id);
    }

    /** @test */
    public function test_cannot_update_installment_payment()
    {
        $this->actingAs($this->admin);

        $payment = InstallmentPayment::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'installment_plan_id' => $this->plan->id,
        ]);

        $payload = [
            'amount_paid' => 1000,
        ];

        $response = $this->putJson("/api/v1/installment-payments/{$payment->id}", $payload);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_cannot_delete_installment_payment()
    {
        $this->actingAs($this->admin);

        $payment = InstallmentPayment::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'installment_plan_id' => $this->plan->id,
        ]);

        $response = $this->deleteJson("/api/v1/installment-payments/{$payment->id}");

        $response->assertStatus(403);
    }
}
