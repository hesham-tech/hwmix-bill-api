<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitLossReportControllerTest extends TestCase
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

    public function test_can_view_profit_loss_report()
    {
        $this->actingAs($this->admin);

        $saleType = InvoiceType::factory()->create(['code' => 'sale']);
        $purchaseType = InvoiceType::factory()->create(['code' => 'purchase']);

        // Sales
        Invoice::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'invoice_type_id' => $saleType->id,
            'status' => 'confirmed',
            'net_amount' => 1000
        ]);

        // Purchases
        Invoice::factory()->count(1)->create([
            'company_id' => $this->company->id,
            'invoice_type_id' => $purchaseType->id,
            'status' => 'confirmed',
            'net_amount' => 500
        ]);

        $response = $this->getJson('/api/reports/profit-loss');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'revenues', 'costs', 'result'])
            ->assertJsonPath('revenues.total', 2000.0)
            ->assertJsonPath('costs.total', 500.0)
            ->assertJsonPath('result.net_profit', 1500.0);
    }

    public function test_can_view_monthly_comparison()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/profit-loss/monthly-comparison');

        $response->assertStatus(200)
            ->assertJsonStructure(['comparison', 'months_count']);
    }
}
