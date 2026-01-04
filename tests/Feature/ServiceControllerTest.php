<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Service;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
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

    /** @test */
    public function test_can_list_services()
    {
        $this->actingAs($this->admin);

        Service::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_service()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Test Service',
            'description' => 'Test description',
            'default_price' => 100.50,
        ];

        $response = $this->postJson('/api/service', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('services', [
            'name' => 'Test Service',
            'default_price' => 100.50
        ]);
    }

    /** @test */
    public function test_can_show_service()
    {
        $this->actingAs($this->admin);

        $service = Service::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/service/{$service->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $service->id);
    }

    /** @test */
    public function test_can_update_service()
    {
        $this->actingAs($this->admin);

        $service = Service::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'name' => 'Updated Service',
            'default_price' => 150.00
        ];

        $response = $this->putJson("/api/service/{$service->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Updated Service',
            'default_price' => 150.00
        ]);
    }

    /** @test */
    public function test_can_delete_service()
    {
        $this->actingAs($this->admin);

        $service = Service::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/service/delete/{$service->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }
}
