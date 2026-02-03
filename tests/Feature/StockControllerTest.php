<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\Stock;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected ProductVariant $variant;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $product = Product::factory()->create(['company_id' => $this->company->id]);
        $this->variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'company_id' => $this->company->id
        ]);
        $this->warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_stocks()
    {
        $this->actingAs($this->admin);
        Stock::factory()->count(3)->create([
            'variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'company_id' => $this->company->id
        ]);

        $response = $this->getJson('/api/stocks');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_stock_record()
    {
        $this->actingAs($this->admin);

        $payload = [
            'variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 150,
            'status' => 'available',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id
        ];

        $response = $this->postJson('/api/stock', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('stocks', ['quantity' => 150, 'variant_id' => $this->variant->id]);
    }

    public function test_can_show_stock_record()
    {
        $this->actingAs($this->admin);
        $stock = Stock::factory()->create([
            'variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'company_id' => $this->company->id
        ]);

        $response = $this->getJson("/api/stock/{$stock->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $stock->id);
    }

    public function test_can_update_stock_record()
    {
        $this->actingAs($this->admin);
        $stock = Stock::factory()->create([
            'variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'company_id' => $this->company->id,
            'quantity' => 50
        ]);

        $payload = [
            'quantity' => 75,
            'updated_by' => $this->admin->id
        ];

        $response = $this->putJson("/api/stock/{$stock->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 75]);
    }

    public function test_can_delete_stock_record()
    {
        $this->actingAs($this->admin);
        $stock = Stock::factory()->create([
            'variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'company_id' => $this->company->id
        ]);

        $response = $this->deleteJson("/api/stock/{$stock->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_data_isolation_cannot_view_stock_from_another_company()
    {
        $companyB = Company::factory()->create();
        $warehouseB = Warehouse::factory()->create(['company_id' => $companyB->id]);
        $productB = Product::factory()->create(['company_id' => $companyB->id]);
        $variantB = ProductVariant::factory()->create([
            'product_id' => $productB->id,
            'company_id' => $companyB->id
        ]);
        $stockB = Stock::factory()->create([
            'variant_id' => $variantB->id,
            'warehouse_id' => $warehouseB->id,
            'company_id' => $companyB->id
        ]);

        $userA = User::factory()->create(['company_id' => $this->company->id]);
        $userA->givePermissionTo('stocks.view_all');

        $this->actingAs($userA);

        $response = $this->getJson("/api/stock/{$stockB->id}");
        $response->assertStatus(403);
    }
}
