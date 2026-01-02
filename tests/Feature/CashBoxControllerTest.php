<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBoxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected CashBoxType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->admin->givePermissionTo('admin.super');

        $this->type = CashBoxType::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    public function test_can_list_cash_boxes()
    {
        $this->actingAs($this->admin);

        CashBox::factory()->count(3)->sequence(
            ['is_default' => true],
            ['is_default' => false],
            ['is_default' => false],
        )->create([
                    'company_id' => $this->company->id,
                    'cash_box_type_id' => $this->type->id,
                    'user_id' => $this->admin->id,
                ]);

        $response = $this->getJson('/api/cashBoxs');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'total']);
    }

    public function test_can_create_cash_box()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Main Safe',
            'cash_box_type_id' => $this->type->id,
            'initial_balance' => 1000,
            'description' => 'Main company safe',
        ];

        $response = $this->postJson('/api/cashBox', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cash_boxes', ['name' => 'Main Safe']);
    }

    public function test_can_show_cash_box()
    {
        $this->actingAs($this->admin);

        $cashBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->type->id,
            'is_default' => true,
        ]);

        $response = $this->getJson("/api/cashBox/{$cashBox->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $cashBox->id);
    }

    public function test_can_update_cash_box()
    {
        $this->actingAs($this->admin);

        $cashBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->type->id,
            'is_default' => true,
        ]);

        $payload = [
            'name' => 'Updated Safe Name',
        ];

        $response = $this->putJson("/api/cashBox/{$cashBox->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cash_boxes', [
            'id' => $cashBox->id,
            'name' => 'Updated Safe Name'
        ]);
    }

    public function test_can_delete_cash_box()
    {
        $this->actingAs($this->admin);

        $cashBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->type->id,
            'is_default' => true,
        ]);

        $response = $this->deleteJson("/api/cashBox/{$cashBox->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cash_boxes', ['id' => $cashBox->id]);
    }

    public function test_can_transfer_funds_between_boxes()
    {
        $this->actingAs($this->admin);

        $fromBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->type->id,
            'balance' => 5000,
            'user_id' => $this->admin->id,
            'is_default' => true,
        ]);

        $toBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'cash_box_type_id' => $this->type->id,
            'balance' => 0,
            'user_id' => $this->admin->id,
            'is_default' => false,
        ]);

        $payload = [
            'cash_box_id' => $fromBox->id,
            'to_cash_box_id' => $toBox->id,
            'amount' => 1000,
            'to_user_id' => $this->admin->id,
            'description' => 'Test transfer',
        ];

        $response = $this->postJson('/api/cashBox/transfer', $payload);

        $response->assertStatus(200);
        $this->assertEquals(4000, $fromBox->fresh()->balance);
        $this->assertEquals(1000, $toBox->fresh()->balance);
    }
}
