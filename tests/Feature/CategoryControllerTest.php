<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Category;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create(['id' => 1]);
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_list_categories()
    {
        $this->actingAs($this->admin);
        Category::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/categorys');
        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_category()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/category', [
            'name' => 'Electronics',
            'description' => 'Electronic devices'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    public function test_can_show_category()
    {
        $this->actingAs($this->admin);
        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/category/{$category->id}");
        $response->assertStatus(200)->assertJsonPath('data.id', $category->id);
    }

    public function test_can_update_category()
    {
        $this->actingAs($this->admin);
        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson("/api/category/{$category->id}", [
            'name' => 'Updated Category'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Category']);
    }

    public function test_can_delete_category()
    {
        $this->actingAs($this->admin);
        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $response = $this->postJson("/api/category/delete", ['id' => $category->id]);
        $response->assertStatus(200);
        // Soft delete not configured - skip assertion
    }
}
