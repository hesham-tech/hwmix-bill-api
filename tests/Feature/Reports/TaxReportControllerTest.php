<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxReportControllerTest extends TestCase
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

    public function test_can_view_tax_report_index()
    {
        $this->actingAs($this->admin);

        $saleType = InvoiceType::factory()->create([
            'code' => 'sale',
            'company_id' => $this->company->id
        ]);
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'invoice_type_id' => $saleType->id,
            'status' => 'confirmed',
            'total_tax' => 150,
            'created_at' => now()->startOfMonth()->addDays(2)
        ]);

        $response = $this->getJson('/api/reports/tax');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'summary']);

        $this->assertEquals(150, $response->json('summary.tax_collected'));
    }

    public function test_can_view_collected_tax()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/tax/collected');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'total_collected', 'invoices']);
    }

    public function test_can_view_paid_tax()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/tax/paid');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'total_paid', 'invoices']);
    }

    public function test_can_view_net_tax()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/tax/net');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'collected', 'paid', 'net_tax', 'recommendation']);
    }
}
