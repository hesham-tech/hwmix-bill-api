<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeControllerTest extends TestCase
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

    public function test_can_list_attributes()
    {
        $this->actingAs($this->admin);
        Attribute::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/attributes');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_attribute_with_values()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Color',
            'values' => [
                ['name' => 'Red', 'color' => '#FF0000'],
                ['name' => 'Blue', 'color' => '#0000FF'],
            ]
        ];

        $response = $this->postJson('/api/attribute', $payload);

        $response->assertStatus(200); // Controller returns 200 for success in store
        $this->assertDatabaseHas('attributes', ['name' => 'Color']);
        $this->assertDatabaseHas('attribute_values', ['name' => 'Red', 'color' => '#FF0000']);
        $this->assertDatabaseHas('attribute_values', ['name' => 'Blue', 'color' => '#0000FF']);
    }

    public function test_can_show_attribute()
    {
        $this->actingAs($this->admin);
        $attribute = Attribute::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/attribute/{$attribute->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $attribute->id);
    }

    public function test_can_update_attribute_and_values()
    {
        $this->actingAs($this->admin);
        $attribute = Attribute::factory()->create(['company_id' => $this->company->id, 'name' => 'Old Name']);
        $val1 = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'name' => 'Val 1', 'company_id' => $this->company->id]);
        $val2 = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'name' => 'Val 2', 'company_id' => $this->company->id]);

        $payload = [
            'name' => 'New Name',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'values' => [
                ['id' => $val1->id, 'name' => 'Updated Val 1', 'color' => '#000000'],
                ['name' => 'New Val 3', 'color' => '#FFFFFF'],
            ]
        ];

        $response = $this->putJson("/api/attribute/{$attribute->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'name' => 'New Name']);
        $this->assertDatabaseHas('attribute_values', ['id' => $val1->id, 'name' => 'Updated Val 1']);
        $this->assertDatabaseHas('attribute_values', ['name' => 'New Val 3']);
        $this->assertSoftDeleted('attribute_values', ['id' => $val2->id]);
    }

    public function test_can_delete_attribute()
    {
        $this->actingAs($this->admin);
        $attribute = Attribute::factory()->create(['company_id' => $this->company->id]);
        $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/attribute/{$attribute->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('attributes', ['id' => $attribute->id]);
        $this->assertDatabaseMissing('attribute_values', ['id' => $value->id]);
    }

    public function test_cannot_delete_attribute_in_use()
    {
        $this->actingAs($this->admin);
        $attribute = Attribute::factory()->create(['company_id' => $this->company->id]);

        // Mock product variant using this attribute
        $variant = ProductVariant::factory()->create(['company_id' => $this->company->id]);
        $attribute->productVariants()->attach($variant->id, [
            'attribute_value_id' => AttributeValue::factory()->create(['attribute_id' => $attribute->id])->id,
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id
        ]);

        $response = $this->deleteJson("/api/attribute/{$attribute->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
    }

    public function test_data_isolation_cannot_view_attribute_from_another_company()
    {
        $companyB = Company::factory()->create();
        $attributeB = Attribute::factory()->create(['company_id' => $companyB->id]);

        $userA = User::factory()->create(['company_id' => $this->company->id]);
        $userA->givePermissionTo('attributes.view_all');

        $this->actingAs($userA);

        $response = $this->getJson("/api/attribute/{$attributeB->id}");
        $response->assertStatus(403);

        $response = $this->getJson('/api/attributes');
        $response->assertJsonMissing(['name' => $attributeB->name]);
    }
}
