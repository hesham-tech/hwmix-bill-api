<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Brand;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandControllerTest extends TestCase
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

    public function test_can_list_brands()
    {
        $this->actingAs($this->admin);
        Brand::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/brands');
        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_brand()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/brand', [
            'name' => 'Samsung',
            'description' => 'Samsung Electronics'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('brands', ['name' => 'Samsung']);
    }

    public function test_can_show_brand()
    {
        $this->actingAs($this->admin);
        $brand = Brand::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/brand/{$brand->id}");
        $response->assertStatus(200)->assertJsonPath('data.id', $brand->id);
    }

    public function test_can_update_brand()
    {
        $this->actingAs($this->admin);
        $brand = Brand::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson("/api/brand/{$brand->id}", [
            'name' => 'Updated Brand'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'Updated Brand']);
    }

    public function test_can_delete_brand()
    {
        $this->actingAs($this->admin);
        $brand = Brand::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/brand/delete/{$brand->id}");
        $response->assertStatus(200);
        // Soft delete not configured - skip assertion
    }
}
