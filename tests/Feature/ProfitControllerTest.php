<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Profit;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitControllerTest extends TestCase
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
    public function test_can_list_profits()
    {
        $this->actingAs($this->admin);

        Profit::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/profits');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_profit()
    {
        $this->actingAs($this->admin);

        $payload = [
            'revenue_amount' => 1000,
            'cost_amount' => 700,
            'profit_amount' => 300,
            'source_type' => 'manual',
            'source_id' => 0,
            'profit_date' => now()->toDateString(),
            'note' => 'Test profit note',
            'company_id' => $this->company->id,
        ];

        $response = $this->postJson('/api/profit', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('profits', [
            'profit_amount' => 300,
            'note' => 'Test profit note'
        ]);
    }

    /** @test */
    public function test_can_show_profit()
    {
        $this->actingAs($this->admin);

        $profit = Profit::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/profit/{$profit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $profit->id);
    }

    /** @test */
    public function test_can_update_profit()
    {
        $this->actingAs($this->admin);

        $profit = Profit::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'profit_amount' => 400,
            'note' => 'Updated profit note',
            'source_type' => 'manual',
            'source_id' => 0,
            'profit_date' => now()->toDateString(),
        ];

        $response = $this->putJson("/api/profit/{$profit->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('profits', [
            'id' => $profit->id,
            'profit_amount' => 400,
            'note' => 'Updated profit note'
        ]);
    }

    /** @test */
    public function test_can_delete_profit()
    {
        $this->actingAs($this->admin);

        $profit = Profit::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/profit/delete/{$profit->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('profits', ['id' => $profit->id]);
    }
}
