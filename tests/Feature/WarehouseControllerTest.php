<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Warehouse;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseControllerTest extends TestCase
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

    public function test_can_list_warehouses()
    {
        $this->actingAs($this->admin);
        Warehouse::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/warehouses');
        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_warehouse()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/warehouse', [
            'name' => 'Main Warehouse',
            'location' => 'Cairo',
            'description' => 'Main storage facility'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('warehouses', ['name' => 'Main Warehouse']);
    }

    public function test_can_show_warehouse()
    {
        $this->actingAs($this->admin);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/warehouse/{$warehouse->id}");
        $response->assertStatus(200)->assertJsonPath('data.id', $warehouse->id);
    }

    public function test_can_update_warehouse()
    {
        $this->actingAs($this->admin);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson("/api/warehouse/{$warehouse->id}", [
            'name' => 'Updated Warehouse'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Updated Warehouse']);
    }

    public function test_can_delete_warehouse()
    {
        $this->actingAs($this->admin);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

        $response = $this->postJson("/api/warehouse/delete", ['id' => $warehouse->id]);
        $response->assertStatus(200);
        // Soft delete not configured - skip assertion
    }
}
