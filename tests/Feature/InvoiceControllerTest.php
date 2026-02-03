<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\InvoiceType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\CashBox;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $product;
    protected $variant;
    protected $cashBox;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed all permissions from config
        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->user->company_id = $this->company->id;
        $this->user->save();

        // Grant super admin permission to bypass other checks
        $this->user->givePermissionTo('admin.super');

        // Setup financial data for the user
        $cashBoxType = \App\Models\CashBoxType::factory()->create(['name' => 'Cash']);
        $this->cashBox = \App\Models\CashBox::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'cash_box_type_id' => $cashBoxType->id,
            'balance' => 10000,
            'is_default' => true
        ]);

        $this->actingAs($this->user);

        $this->product = Product::factory()->create(['company_id' => $this->company->id]);
        $this->variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'company_id' => $this->company->id
        ]);

        // Setup stock for the variant
        $warehouse = \App\Models\Warehouse::factory()->create(['company_id' => $this->company->id]);
        \App\Models\Stock::factory()->create([
            'variant_id' => $this->variant->id,
            'warehouse_id' => $warehouse->id,
            'company_id' => $this->company->id,
            'quantity' => 100
        ]);
    }

    public function test_resolves_invoice_creation_service_for_sale()
    {
        $invoiceType = InvoiceType::factory()->create([
            'code' => 'sale',
            'company_id' => $this->company->id
        ]);

        $payload = [
            'user_id' => $this->user->id,
            'invoice_type_id' => $invoiceType->id,
            'invoice_type_code' => 'sale',
            'invoice_number' => 'INV-001',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'gross_amount' => 1500,
            'net_amount' => 1500,
            'paid_amount' => 500,
            'status' => 'draft',
            'company_id' => $this->company->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'unit_price' => 750,
                    'discount' => 0,
                    'total' => 1500,
                ],
            ],
        ];

        $response = $this->postJson('/api/invoice', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['invoice_type_id' => $invoiceType->id]);
    }

    public function test_resolves_invoice_type_code_if_not_provided()
    {
        $invoiceType = InvoiceType::factory()->create([
            'code' => 'purchase',
            'company_id' => $this->company->id
        ]);

        $payload = [
            'user_id' => $this->user->id,
            'invoice_type_id' => $invoiceType->id,
            'invoice_number' => 'INV-002',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'gross_amount' => 2000,
            'net_amount' => 2000,
            'paid_amount' => 2000,
            'status' => 'draft',
            'company_id' => $this->company->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => $this->product->name,
                    'quantity' => 1,
                    'unit_price' => 2000,
                    'discount' => 0,
                    'total' => 2000,
                ],
            ],
        ];

        $response = $this->postJson('/api/invoice', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['invoice_type_id' => $invoiceType->id]);
    }
}
