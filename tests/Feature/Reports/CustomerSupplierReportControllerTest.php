<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupplierReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_view_top_customers()
    {
        $this->actingAs($this->admin);

        $saleType = InvoiceType::factory()->create(['code' => 'sale']);
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'invoice_type_id' => $saleType->id,
            'status' => 'confirmed',
            'net_amount' => 5000,
            'user_id' => $this->admin->id
        ]);

        $response = $this->getJson('/api/reports/customers/top');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'top_customers']);

        $this->assertEquals($this->admin->full_name, $response->json('top_customers.0.customer_name'));
    }

    public function test_can_view_customer_supplier_balances()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/customers/debts'); // Map to index or customerDebts in route? 
        // Route is /reports/customers/debts -> customerDebts

        $response->assertStatus(200)
            ->assertJsonStructure(['total_debt', 'customer_debts']);
    }

    public function test_can_view_supplier_debts()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/suppliers/debts');

        $response->assertStatus(200)
            ->assertJsonStructure(['total_debt_to_suppliers', 'supplier_debts']);
    }

    public function test_can_view_customer_performance()
    {
        $this->actingAs($this->admin);

        $customer = User::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/reports/customers/performance?user_id={$customer->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'period', 'sales_stats', 'monthly_trend', 'top_products']);
    }
}
