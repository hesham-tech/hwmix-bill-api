<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Warehouse;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Category $category;
    protected Brand $brand;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create(['id' => 1]);
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->admin->givePermissionTo('admin.super');

        $this->category = Category::factory()->create(['company_id' => $this->company->id]);
        $this->brand = Brand::factory()->create(['company_id' => $this->company->id]);
        $this->warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_products()
    {
        $this->actingAs($this->admin);

        Product::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_product()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Test Product',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'product_type' => 'physical',
            'price' => 100,
            'cost' => 50,
            'variants' => [
                [
                    'sku' => 'TEST-001',
                    'price' => 100,
                    'cost' => 50,
                    'stock' => 10,
                    'stocks' => [
                        [
                            'warehouse_id' => $this->warehouse->id,
                            'quantity' => 10,
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/product', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    }

    public function test_can_show_product()
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson("/api/product/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_can_update_product()
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
        ]);

        $payload = [
            'name' => 'Updated Product',
            'price' => 150,
        ];

        $response = $this->putJson("/api/product/{$product->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product'
        ]);
    }

    public function test_can_delete_product()
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->deleteJson("/api/product/delete/{$product->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
