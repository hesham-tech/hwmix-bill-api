<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\InvoiceType;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTypeControllerTest extends TestCase
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
    public function test_can_list_invoice_types()
    {
        $this->actingAs($this->admin);

        InvoiceType::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/invoice-types');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_invoice_type()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'فاتورة مبيعات',
            'code' => 'SALE_001',
            'context' => 'sale',
        ];

        $response = $this->postJson('/api/invoice-type', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoice_types', [
            'code' => 'SALE_001'
        ]);
    }

    /** @test */
    public function test_can_show_invoice_type()
    {
        $this->actingAs($this->admin);

        $type = InvoiceType::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/invoice-type/{$type->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $type->id);
    }

    /** @test */
    public function test_can_update_invoice_type()
    {
        $this->actingAs($this->admin);

        $type = InvoiceType::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'name' => 'Old Name',
        ]);

        $payload = [
            'name' => 'New Name',
        ];

        $response = $this->putJson("/api/invoice-type/{$type->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoice_types', [
            'id' => $type->id,
            'name' => 'New Name'
        ]);
    }

    /** @test */
    public function test_can_delete_invoice_type()
    {
        $this->actingAs($this->admin);

        $type = InvoiceType::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/invoice-type/delete/{$type->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('invoice_types', ['id' => $type->id]);
    }
}
