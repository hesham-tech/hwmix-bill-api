<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Invoice $invoice;
    protected InstallmentPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $invoiceType = InvoiceType::factory()->create(['company_id' => $this->company->id]);
        $this->invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'invoice_type_id' => $invoiceType->id,
            'user_id' => $this->admin->id,
            'gross_amount' => 10000,
            'net_amount' => 10000,
        ]);

        $this->plan = InstallmentPlan::factory()->create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->invoice->id,
            'name' => 'Test Plan',
            'number_of_installments' => 3,
        ]);
    }

    public function test_can_list_installments()
    {
        $this->actingAs($this->admin);

        Installment::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $response = $this->getJson('/api/installments');
        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_installment()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/installment', [
            'invoice_id' => $this->invoice->id,
            'installment_plan_id' => $this->plan->id,
            'amount' => 1000,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        dump(Installment::all()->toArray());
        $this->assertDatabaseHas('installments', ['amount' => 1000]);
    }

    public function test_can_show_installment()
    {
        $this->actingAs($this->admin);

        $installment = Installment::factory()->create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $response = $this->getJson("/api/installment/{$installment->id}");
        if ($response->status() !== 200) {
            dd($response->json());
        }
        $response->assertStatus(200)->assertJsonPath('data.id', $installment->id);
    }

    public function test_can_update_installment()
    {
        $this->actingAs($this->admin);

        $installment = Installment::factory()->create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $response = $this->putJson("/api/installment/{$installment->id}", [
            'amount' => 1500,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('installments', ['id' => $installment->id, 'amount' => 1500]);
    }

    public function test_can_delete_installment()
    {
        $this->actingAs($this->admin);

        $installment = Installment::factory()->create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $response = $this->deleteJson("/api/installment/{$installment->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('installments', ['id' => $installment->id]);
    }
}
