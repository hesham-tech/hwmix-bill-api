<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportControllerTest extends TestCase
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

    public function test_can_view_sales_report_index()
    {
        $this->actingAs($this->admin);

        $saleType = InvoiceType::factory()->create(['code' => 'sale']);
        Invoice::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'invoice_type_id' => $saleType->id,
            'status' => 'confirmed',
            'net_amount' => 100
        ]);

        $response = $this->getJson('/api/reports/sales');

        $response->assertStatus(200)
            ->assertJsonStructure(['report', 'summary', 'filters'])
            ->assertJsonPath('summary.total_amount', 500);
    }

    public function test_can_view_top_products()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/sales/top-products');

        $response->assertStatus(200)
            ->assertJsonStructure(['top_products', 'period']);
    }

    public function test_can_view_top_customers()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/sales/top-customers');

        $response->assertStatus(200)
            ->assertJsonStructure(['top_customers', 'period']);
    }

    public function test_can_view_sales_trend()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/sales/trend?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure(['trend', 'period_type']);
    }

    public function test_sales_report_isolation()
    {
        $companyB = Company::factory()->create();
        $saleType = InvoiceType::factory()->create(['code' => 'sale']);

        Invoice::factory()->create([
            'company_id' => $companyB->id,
            'invoice_type_id' => $saleType->id,
            'net_amount' => 1000
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/sales');

        // Assert that companyB's sales are not included in the admin's company report
        $response->assertJsonPath('summary.total_amount', 0);
    }
}
