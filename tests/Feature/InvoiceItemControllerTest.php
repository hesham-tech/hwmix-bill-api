<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Modules\Sales\Models\InvoiceItem;
use Modules\Sales\Models\Invoice;
use Modules\Inventory\Models\Product;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Invoice $invoice;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $this->invoice = Invoice::factory()->create(['company_id' => $this->company->id]);
        $this->product = Product::factory()->create(['company_id' => $this->company->id]);
    }

    /** @test */
    public function test_can_list_invoice_items()
    {
        $this->actingAs($this->admin);

        InvoiceItem::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/invoice-items');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_invoice_item()
    {
        $this->actingAs($this->admin);

        $payload = [
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'name' => 'Test Item',
            'quantity' => 5,
            'unit_price' => 100,
            'subtotal' => 500,
            'total' => 500,
        ];

        $response = $this->postJson('/api/v1/invoice-item', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $this->invoice->id,
            'quantity' => 5
        ]);
    }

    /** @test */
    public function test_can_show_invoice_item()
    {
        $this->actingAs($this->admin);

        $item = InvoiceItem::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/v1/invoice-item/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $item->id);
    }

    /** @test */
    public function test_can_update_invoice_item()
    {
        $this->actingAs($this->admin);

        $item = InvoiceItem::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'quantity' => 5,
        ]);

        $payload = [
            'quantity' => 10,
        ];

        $response = $this->putJson("/api/v1/invoice-item/{$item->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoice_items', [
            'id' => $item->id,
            'quantity' => 10
        ]);
    }

    /** @test */
    public function test_can_delete_invoice_item()
    {
        $this->actingAs($this->admin);

        $item = InvoiceItem::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/v1/invoice-item/{$item->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('invoice_items', ['id' => $item->id]);
    }
}
