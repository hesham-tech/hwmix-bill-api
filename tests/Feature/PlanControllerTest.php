<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Plan;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanControllerTest extends TestCase
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
    public function test_can_list_plans()
    {
        $this->actingAs($this->admin);

        Plan::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/plans');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_plan()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Premium Plan',
            'description' => 'Premium subscription',
            'price' => 999.99,
            'currency' => 'EGP',
            'duration' => 12,
            'duration_unit' => 'month',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('plans', [
            'name' => 'Premium Plan',
            'price' => 999.99
        ]);
    }

    /** @test */
    public function test_can_show_plan()
    {
        $this->actingAs($this->admin);

        $plan = Plan::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/plans/{$plan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $plan->id);
    }

    /** @test */
    public function test_can_update_plan()
    {
        $this->actingAs($this->admin);

        $plan = Plan::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'name' => 'Updated Plan Name',
            'price' => 1299.99
        ];

        $response = $this->putJson("/api/plans/{$plan->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Updated Plan Name',
            'price' => 1299.99
        ]);
    }

    /** @test */
    public function test_can_delete_plan()
    {
        $this->actingAs($this->admin);

        $plan = Plan::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/plans/{$plan->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }
}
