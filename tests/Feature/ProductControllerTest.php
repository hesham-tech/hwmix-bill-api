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
        $this->company = Company::factory()->create();
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
            'variants' => [
                [
                    'sku' => 'TEST-001',
                    'retail_price' => 100,
                    'wholesale_price' => 50,
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
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'variants' => [
                [
                    'sku' => 'UPDATED-SKU',
                    'retail_price' => 150,
                    'stocks' => [
                        [
                            'warehouse_id' => $this->warehouse->id,
                            'quantity' => 20,
                        ]
                    ]
                ]
            ]
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
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_user_cannot_view_products_from_another_company()
    {
        $otherCompany = Company::factory()->create();
        $otherProduct = Product::factory()->create([
            'company_id' => $otherCompany->id,
            'category_id' => Category::factory()->create(['company_id' => $otherCompany->id])->id,
        ]);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->givePermissionTo('products.view_all'); // view_all within company

        $this->actingAs($user);

        $response = $this->getJson("/api/product/{$otherProduct->id}");
        $response->assertStatus(403);

        $response = $this->getJson('/api/products');
        $response->assertJsonMissing(['name' => $otherProduct->name]);
    }

    public function test_cannot_create_product_with_duplicate_sku()
    {
        $this->actingAs($this->admin);

        ProductVariant::factory()->create([
            'sku' => 'DUPE-SKU',
            'company_id' => $this->company->id
        ]);

        $payload = [
            'name' => 'Dupe Product',
            'category_id' => $this->category->id,
            'variants' => [
                ['sku' => 'DUPE-SKU', 'retail_price' => 100]
            ]
        ];

        $response = $this->postJson('/api/product', $payload);
        $response->assertStatus(422);
    }
}
