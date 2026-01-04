<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected User $customer;
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
    }

    /** @test */
    public function test_can_list_installment_plans()
    {
        $this->actingAs($this->admin);

        InstallmentPlan::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $response = $this->getJson('/api/installment-plans');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_installment_plan()
    {
        // Remove withoutExceptionHandling to see error
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Test Plan',
            'user_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'total_amount' => 3000,
            'down_payment' => 500,
            'remaining_amount' => 2500, // total - down_payment
            'installment_count' => 5,
            'number_of_installments' => 5, // same as installment_count
            'installment_amount' => 500,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(5)->toDateString(),
            'due_day' => 1,
            'status' => 'active',
        ];

        $response = $this->postJson('/api/installment-plan', $payload);

        dump($response->json());
        $response->assertStatus(201);
        $this->assertDatabaseHas('installment_plans', [
            'name' => 'Test Plan',
            'total_amount' => 3000
        ]);
    }

    /** @test */
    public function test_can_show_installment_plan()
    {
        $this->actingAs($this->admin);

        $plan = InstallmentPlan::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $response = $this->getJson("/api/installment-plan/{$plan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $plan->id);
    }

    /** @test */
    public function test_can_update_installment_plan()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $plan = InstallmentPlan::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $payload = [
            'notes' => 'Updated notes'
        ];

        $response = $this->putJson("/api/installment-plan/{$plan->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('installment_plans', [
            'id' => $plan->id,
            'notes' => 'Updated notes'
        ]);
    }

    /** @test */
    public function test_can_delete_installment_plan()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $plan = InstallmentPlan::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'user_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $response = $this->deleteJson("/api/installment-plan/delete/{$plan->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('installment_plans', ['id' => $plan->id]);
    }
}
