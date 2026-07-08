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

        $types = InvoiceType::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        foreach ($types as $type) {
            $this->company->invoiceTypes()->attach($type->id, ['is_active' => true]);
        }

        $response = $this->getJson('/api/v1/invoice-types');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_cannot_create_invoice_type()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'فاتورة مبيعات',
            'code' => 'SALE_001',
            'context' => 'sale',
        ];

        $response = $this->postJson('/api/v1/invoice-types', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('invoice_types', [
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

        $this->company->invoiceTypes()->attach($type->id, ['is_active' => true]);

        $response = $this->getJson("/api/v1/invoice-types/{$type->id}");

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

        $this->company->invoiceTypes()->attach($type->id, ['is_active' => true]);

        $payload = [
            'is_active' => false,
        ];

        $response = $this->putJson("/api/v1/invoice-types/{$type->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('company_invoice_type', [
            'invoice_type_id' => $type->id,
            'company_id' => $this->company->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function test_cannot_delete_invoice_type()
    {
        $this->actingAs($this->admin);

        $type = InvoiceType::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/v1/invoice-types/{$type->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('invoice_types', ['id' => $type->id]);
    }
}
