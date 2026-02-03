<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Revenue;
use App\Models\CashBox;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueControllerTest extends TestCase
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
    public function test_can_list_revenues()
    {
        $this->actingAs($this->admin);

        Revenue::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/revenues');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_revenue()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payload = [
            'amount' => 500,
            'source_type' => 'manual',
            'source_id' => 0,
            'revenue_date' => now()->toDateString(),
            'note' => 'Test revenue note',
            'company_id' => $this->company->id,
        ];

        $response = $this->postJson('/api/revenue', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('revenues', [
            'amount' => 500,
            'note' => 'Test revenue note'
        ]);
    }

    /** @test */
    public function test_can_show_revenue()
    {
        $this->actingAs($this->admin);

        $revenue = Revenue::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/revenue/{$revenue->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $revenue->id);
    }

    /** @test */
    public function test_can_update_revenue()
    {
        $this->actingAs($this->admin);

        $revenue = Revenue::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'amount' => 600,
            'note' => 'Updated revenue note',
            'source_type' => 'manual',
            'source_id' => 0,
            'revenue_date' => now()->toDateString(),
        ];

        $response = $this->putJson("/api/revenue/{$revenue->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('revenues', [
            'id' => $revenue->id,
            'amount' => 600,
            'note' => 'Updated revenue note'
        ]);
    }

    /** @test */
    public function test_can_delete_revenue()
    {
        $this->actingAs($this->admin);

        $revenue = Revenue::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/revenue/delete/{$revenue->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('revenues', ['id' => $revenue->id]);
    }
}
