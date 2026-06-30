<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Modules\Inventory\Models\Category;
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
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_list_categories()
    {
        $this->actingAs($this->admin);
        Category::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/v1/categories');
        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);

    }

    public function test_can_create_category()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/categories', [
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

        $response = $this->getJson("/api/v1/categories/{$category->id}");
        $response->assertStatus(200)->assertJsonPath('data.id', $category->id);
    }

    public function test_can_update_category()
    {
        $this->actingAs($this->admin);
        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson("/api/v1/categories/{$category->id}", [
            'name' => 'Updated Category'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Category']);
    }

    public function test_can_delete_category()
    {
        $this->actingAs($this->admin);
        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_children()
    {
        $this->actingAs($this->admin);
        $parent = Category::factory()->create(['company_id' => $this->company->id]);
        Category::factory()->create([
            'company_id' => $this->company->id,
            'parent_id' => $parent->id
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$parent->id}");
        $response->assertStatus(409);
    }

    public function test_user_cannot_view_categories_from_another_company()
    {
        $otherCompany = Company::factory()->create();
        $otherCategory = Category::factory()->create(['company_id' => $otherCompany->id]);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
        ]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo('categories.view_all');

        $this->actingAs($user);

        $response = $this->getJson("/api/v1/categories/{$otherCategory->id}");
        $response->assertStatus(403);

        $response = $this->getJson('/api/v1/categories');
        $response->assertJsonMissing(['name' => $otherCategory->name]);
    }
}
