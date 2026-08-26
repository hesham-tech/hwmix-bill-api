<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Company;
use Modules\Sales\Models\Invoice;
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

                // Mock financial ledgers
        \App\Models\FinancialLedger::insert([
            [
                'entry_date' => now()->toDateString(),
                'company_id' => $this->company->id,
                'account_type' => 'revenue',
                'type' => 'credit',
                'amount' => 1000,
                'source_id' => 1, 'source_type' => 'Modules\Sales\Models\Invoice', 'description' => 'Sale 1',
            ],
            [
                'entry_date' => now()->toDateString(),
                'company_id' => $this->company->id,
                'account_type' => 'revenue',
                'type' => 'credit',
                'amount' => 1000,
                'source_id' => 2, 'source_type' => 'Modules\Sales\Models\Invoice', 'description' => 'Sale 2',
            ],
            [
                'entry_date' => now()->toDateString(),
                'company_id' => $this->company->id,
                'account_type' => 'expense',
                'type' => 'debit',
                'amount' => 500,
                'source_id' => 1, 'source_type' => 'App\Models\Expense',
                'description' => 'General Expense',
            ]
        ]);
        
        \App\Jobs\UpdateDailySalesSummary::dispatchSync(now()->toDateString(), $this->company->id);

        $response = $this->getJson('/api/reports/profit-loss');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'revenues', 'costs', 'result']);
        \App\Jobs\UpdateDailySalesSummary::dispatchSync(now()->toDateString(), $this->company->id);
        $this->assertEquals(2000, $response->json('revenues.total'));
        $this->assertEquals(500, $response->json('costs.total'));
        $this->assertEquals(1500, $response->json('result.net_profit'));
    }

    public function test_can_view_monthly_comparison()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/profit-loss/monthly-comparison');

        $response->assertStatus(200)
            ->assertJsonStructure(['comparison', 'months_count']);
    }
}
