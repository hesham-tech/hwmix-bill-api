<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Payment;
use App\Models\CashBox;
use App\Models\PaymentMethod;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected CashBox $cashBox;
    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $this->cashBox = CashBox::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->admin->id]);
        $this->paymentMethod = PaymentMethod::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_payments()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        Payment::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/payments');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    public function test_can_create_payment()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payload = [
            'user_id' => $this->admin->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1500.75,
            'cash_amount' => 1500.75,
            'credit_amount' => 0.00,
            'method' => 'cash',
            'notes' => 'Test payment notes',
            'is_split' => false,
            'payment_method_id' => $this->paymentMethod->id,
            'cash_box_id' => $this->cashBox->id, // Controller expects this in store method
        ];

        $response = $this->postJson('/api/v1/payments', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', [
            'amount' => 1500.75,
            'notes' => 'Test payment notes'
        ]);
    }

    public function test_can_show_payment()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $payment->id);
    }

    public function test_can_update_payment()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'amount' => 2000,
            'notes' => 'Updated notes'
        ];

        $response = $this->putJson("/api/v1/payments/{$payment->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => 2000,
            'notes' => 'Updated notes'
        ]);
    }

    public function test_can_delete_payment()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $this->postJson('/api/v1/payments',[
            'user_id'=>$this->admin->id,'payment_date'=>now()->toDateString(),'amount'=>1500.75,'cash_amount'=>1500.75,'credit_amount'=>0,
            'method'=>'cash','is_split'=>false,'payment_method_id'=>$this->paymentMethod->id,'cash_box_id'=>$this->cashBox->id,
        ])->assertCreated();
        $payment=Payment::latest('id')->firstOrFail();
        $this->assertNotNull($payment->financial_operation_id);

        $response = $this->deleteJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_legacy_payment_without_a_financial_operation_requires_review_instead_of_unbalanced_deletion(): void {
        $this->actingAs($this->admin);
        $payment=Payment::factory()->create(['company_id'=>$this->company->id,'user_id'=>$this->admin->id,'created_by'=>$this->admin->id,'financial_operation_id'=>null]);
        $this->deleteJson("/api/v1/payments/{$payment->id}")->assertUnprocessable();
        $this->assertDatabaseHas('payments',['id'=>$payment->id]);
    }
}
