<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\CashBoxType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBoxTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_list_cash_box_types()
    {
        $this->actingAs($this->admin);

        CashBoxType::factory()->count(3)->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->getJson('/api/cashBoxTypes');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'total']);
    }

    public function test_can_create_cash_box_type()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Safe Box',
            'description' => 'Standard safe box type',
        ];

        $response = $this->postJson('/api/cashBoxType', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cash_box_types', ['name' => 'Safe Box']);
    }

    public function test_can_show_cash_box_type()
    {
        $this->actingAs($this->admin);

        $type = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->getJson("/api/cashBoxType/{$type->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $type->id);
    }

    public function test_can_update_cash_box_type()
    {
        $this->actingAs($this->admin);

        $type = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $payload = [
            'name' => 'Updated Type Name',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson("/api/cashBoxType/{$type->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cash_box_types', [
            'id' => $type->id,
            'name' => 'Updated Type Name'
        ]);
    }

    public function test_can_toggle_cash_box_type_status()
    {
        $this->actingAs($this->admin);

        $type = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/cashBoxType/{$type->id}/toggle");

        $response->assertStatus(200);
        $this->assertEquals(false, $type->fresh()->is_active);
    }

    public function test_can_delete_cash_box_type()
    {
        $this->actingAs($this->admin);

        $type = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->deleteJson("/api/cashBoxType/{$type->id}", [
            'item_ids' => [$type->id]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cash_box_types', ['id' => $type->id]);
    }
}
