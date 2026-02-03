<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\Stock;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReportControllerTest extends TestCase
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

    public function test_can_view_stock_report_index()
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['company_id' => $this->company->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'company_id' => $this->company->id]);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

        Stock::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => 100,
            'status' => 'available',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/reports/stock');

        if ($response->status() !== 200) {
            fwrite(STDERR, $response->getContent());
        }
        $response->assertStatus(200)
            ->assertJsonStructure(['report', 'summary', 'filters']);
    }

    public function test_can_view_stock_valuation()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/stock/valuation');

        $response->assertStatus(200)
            ->assertJsonStructure(['valuation', 'summary']);
    }

    public function test_can_view_low_stock()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/stock/low-stock?threshold=10');

        $response->assertStatus(200)
            ->assertJsonStructure(['low_stock_items', 'threshold', 'count']);
    }

    public function test_can_view_inactive_stock()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/reports/stock/inactive?days=30');

        $response->assertStatus(200)
            ->assertJsonStructure(['inactive_items', 'days_threshold', 'count']);
    }
}
