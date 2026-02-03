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
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_list_categories()
    {
        $this->actingAs($this->admin);
        Category::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/categories');
        if ($response->status() !== 200) {
            file_put_contents(base_path('debug_category.json'), $response->content());
        }
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

        $response = $this->postJson("/api/category/delete", ['id' => $parent->id]);
        $response->assertStatus(409);
    }

    public function test_user_cannot_view_categories_from_another_company()
    {
        $otherCompany = Company::factory()->create();
        $otherCategory = Category::factory()->create(['company_id' => $otherCompany->id]);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->givePermissionTo('categories.view_all');

        $this->actingAs($user);

        $response = $this->getJson("/api/category/{$otherCategory->id}");
        $response->assertStatus(403);

        $response = $this->getJson('/api/categories');
        $response->assertJsonMissing(['name' => $otherCategory->name]);
    }
}
