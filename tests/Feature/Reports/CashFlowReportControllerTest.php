<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Company;
use App\Models\CashBox;
use App\Models\Transaction;
use App\Models\CashBoxType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowReportControllerTest extends TestCase
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

    public function test_can_view_cash_flow_report()
    {
        $this->actingAs($this->admin);

        $cashBox = CashBox::factory()->create(['company_id' => $this->company->id]);

        // Deposit
        Transaction::create([
            'company_id' => $this->company->id,
            'cashbox_id' => $cashBox->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'type' => 'deposit',
            'amount' => 1000,
            'balance_before' => 0,
            'balance_after' => 1000,
        ]);

        // Withdraw
        Transaction::create([
            'company_id' => $this->company->id,
            'cashbox_id' => $cashBox->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'type' => 'withdraw',
            'amount' => 300,
            'balance_before' => 1000,
            'balance_after' => 700,
        ]);

        $response = $this->getJson('/api/reports/cash-flow');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'breakdown', 'by_type', 'transactions'])
            ->assertJsonPath('breakdown.net_cash_flow', 700.0);
    }

    public function test_can_view_cash_flow_by_cash_box()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/cash-flow/by-cash-box');

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'by_cash_box']);
    }

    public function test_can_view_cash_flow_summary()
    {
        $this->actingAs($this->admin);

        $type = CashBoxType::factory()->create();
        CashBox::factory()->create(['company_id' => $this->company->id, 'cash_box_type_id' => $type->id]);

        $response = $this->getJson('/api/reports/cash-flow/summary');

        $response->assertStatus(200)
            ->assertJsonStructure(['total_cash_boxes', 'total_balance', 'by_type', 'details']);
    }
}
