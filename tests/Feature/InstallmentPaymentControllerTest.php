<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected User $customer;
    protected InstallmentPlan $plan;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $this->customer = User::factory()->create(['company_id' => $this->company->id]);
        $this->invoice = Invoice::factory()->create(['company_id' => $this->company->id]);
        $this->plan = InstallmentPlan::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);
    }

    /** @test */
    public function test_can_list_installments()
    {
        $this->actingAs($this->admin);

        Installment::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'installment_plan_id' => $this->plan->id,
        ]);

        $response = $this->getJson('/api/installment-payments');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_installment()
    {
        $this->actingAs($this->admin);

        $payload = [
            'installment_plan_id' => $this->plan->id,
            'user_id' => $this->customer->id,
            'installment_number' => 1,
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => 500,
            'status' => 'pending',
            'remaining' => 500,
        ];

        $response = $this->postJson('/api/installment-payment', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('installments', [
            'installment_plan_id' => $this->plan->id,
            'amount' => 500
        ]);
    }

    /** @test */
    public function test_can_show_installment()
    {
        $this->actingAs($this->admin);

        $installment = Installment::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'installment_plan_id' => $this->plan->id,
        ]);

        $response = $this->getJson("/api/installment-payment/{$installment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $installment->id);
    }

    /** @test */
    public function test_can_update_installment()
    {
        $this->actingAs($this->admin);

        $installment = Installment::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'installment_plan_id' => $this->plan->id,
            'status' => 'pending',
        ]);

        $payload = [
            'status' => 'paid',
        ];

        $response = $this->putJson("/api/installment-payment/{$installment->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('installments', [
            'id' => $installment->id,
            'status' => 'paid'
        ]);
    }

    /** @test */
    public function test_can_delete_installment()
    {
        $this->actingAs($this->admin);

        $installment = Installment::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'installment_plan_id' => $this->plan->id,
        ]);

        $response = $this->deleteJson("/api/installment-payment/delete/{$installment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('installments', ['id' => $installment->id]);
    }
}
