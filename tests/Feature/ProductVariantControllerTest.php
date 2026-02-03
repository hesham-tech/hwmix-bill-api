<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Stock;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $this->product = Product::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_product_variants()
    {
        $this->actingAs($this->admin);
        ProductVariant::factory()->count(3)->create([
            'product_id' => $this->product->id,
            'company_id' => $this->company->id
        ]);

        $response = $this->getJson('/api/product-variants');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_product_variant_with_attributes_and_stocks()
    {
        $this->actingAs($this->admin);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);
        $attribute = Attribute::factory()->create(['company_id' => $this->company->id]);
        $value = AttributeValue::factory()->create([
            'attribute_id' => $attribute->id,
            'company_id' => $this->company->id
        ]);

        $payload = [
            'product_id' => $this->product->id,
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'status' => 'active',
            'sku' => 'TEST-SKU-123',
            'retail_price' => 500,
            'attributes' => [
                ['attribute_id' => $attribute->id, 'attribute_value_id' => $value->id]
            ],
            'stocks' => [
                ['warehouse_id' => $warehouse->id, 'quantity' => 10]
            ]
        ];

        $response = $this->postJson('/api/product-variant', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_variants', ['sku' => 'TEST-SKU-123']);
        $this->assertDatabaseHas('product_variant_attributes', ['attribute_value_id' => $value->id]);
        $this->assertDatabaseHas('stocks', ['warehouse_id' => $warehouse->id, 'quantity' => 10]);
    }

    public function test_can_show_product_variant()
    {
        $this->actingAs($this->admin);
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'company_id' => $this->company->id
        ]);

        $response = $this->getJson("/api/product-variant/{$variant->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $variant->id);
    }

    public function test_can_update_product_variant_stocks()
    {
        $this->actingAs($this->admin);
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'company_id' => $this->company->id
        ]);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);
        $stock = Stock::factory()->create([
            'variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'company_id' => $this->company->id,
            'quantity' => 5
        ]);

        $payload = [
            'product_id' => $this->product->id,
            'retail_price' => 600,
            'stocks' => [
                ['id' => $stock->id, 'warehouse_id' => $warehouse->id, 'quantity' => 20]
            ]
        ];

        $response = $this->putJson("/api/product-variant/{$variant->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 20]);
    }

    public function test_cannot_delete_variant_with_active_stock()
    {
        $this->actingAs($this->admin);
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'company_id' => $this->company->id
        ]);
        Stock::factory()->create([
            'variant_id' => $variant->id,
            'company_id' => $this->company->id,
            'quantity' => 10
        ]);

        $response = $this->deleteJson("/api/product-variant/{$variant->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_can_delete_variant_with_zero_stock()
    {
        $this->actingAs($this->admin);
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'company_id' => $this->company->id
        ]);
        Stock::factory()->create([
            'variant_id' => $variant->id,
            'company_id' => $this->company->id,
            'quantity' => 0
        ]);

        $response = $this->deleteJson("/api/product-variant/{$variant->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    }

    public function test_data_isolation_cannot_view_variant_from_another_company()
    {
        $companyB = Company::factory()->create();
        $productB = Product::factory()->create(['company_id' => $companyB->id]);
        $variantB = ProductVariant::factory()->create([
            'product_id' => $productB->id,
            'company_id' => $companyB->id
        ]);

        $userA = User::factory()->create(['company_id' => $this->company->id]);
        $userA->givePermissionTo('product_variants.view_all');

        $this->actingAs($userA);

        $response = $this->getJson("/api/product-variant/{$variantB->id}");
        if ($response->status() !== 403)
            dd($response->json());
        $response->assertStatus(403);
    }
}
