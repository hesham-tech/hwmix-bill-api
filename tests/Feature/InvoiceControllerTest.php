<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\InvoiceType;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use App\Models\CashBox;
use Modules\Inventory\Models\Stock;
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
        $this->user->active_company_id = $this->company->id;
        $this->user->save();

        // Link user to company (Membership)
        \App\Models\CompanyUser::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Scope Spatie permissions to the test company
        setPermissionsTeamId($this->company->id);

        // Grant super admin permission to bypass other checks
        $this->user->givePermissionTo('admin.super');

        // Setup financial data for the user
        $cashBoxType = \App\Models\CashBoxType::factory()->create(['name' => 'Cash']);
        $this->cashBox = CashBox::factory()->create([
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
        $warehouse = \Modules\Inventory\Models\Warehouse::factory()->create(['company_id' => $this->company->id]);
        Stock::factory()->create([
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

        $response = $this->postJson('/api/v1/invoices', $payload);

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

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['invoice_type_id' => $invoiceType->id]);
    }

    /**
     * اختبار: إنشاء فاتورة سريعة للعميل النقدي الافتراضي بنجاح (دفع كامل).
     */
    public function test_creates_quick_invoice_for_default_cash_customer_successfully()
    {
        // تهيئة العميل النقدي الافتراضي للشركة
        $cashCustomer = $this->company->getOrCreateDefaultCashCustomer();
        $invoiceType = InvoiceType::factory()->create(['code' => 'sale']);
        $this->company->invoiceTypes()->syncWithoutDetaching([$invoiceType->id => ['is_active' => true]]);

        $payload = [
            'invoice_type_id' => $invoiceType->id,
            'invoice_type_code' => 'sale',
            'gross_amount' => 500,
            'net_amount' => 500,
            'paid_amount' => 500, // دفع كامل - شرط أساسي للعميل النقدي
            'remaining_amount' => 0,
            'user_id' => $cashCustomer->id,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'cash_box_id' => $this->cashBox->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => $this->product->name,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'discount' => 0,
                    'total' => 500,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['user_id' => $cashCustomer->id, 'net_amount' => 500]);

        // التحقق من عدم تسجيل أي ذمم أو مديونيات على حساب العميل النقدي
        $cashCustomer->refresh();
        $this->assertEquals(0, (float)$cashCustomer->balance ?? 0,
            'يجب ألا يكون للعميل النقدي أي ذمم أو رصيد مسجل.');
    }

    /**
     * اختبار: رفض الفاتورة السريعة للعميل النقدي إذا كانت غير مدفوعة بالكامل.
     */
    public function test_rejects_quick_invoice_for_cash_customer_if_not_fully_paid()
    {
        $cashCustomer = $this->company->getOrCreateDefaultCashCustomer();
        $invoiceType = InvoiceType::factory()->create(['code' => 'sale']);
        $this->company->invoiceTypes()->syncWithoutDetaching([$invoiceType->id => ['is_active' => true]]);

        $payload = [
            'invoice_type_id' => $invoiceType->id,
            'invoice_type_code' => 'sale',
            'gross_amount' => 500,
            'net_amount' => 500,
            'paid_amount' => 100, // دفع جزئي - يجب رفضه
            'remaining_amount' => 400,
            'user_id' => $cashCustomer->id,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => $this->product->name,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'discount' => 0,
                    'total' => 500,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['paid_amount']);
    }

    /**
     * اختبار: رفض التقسيط للعميل النقدي الافتراضي.
     */
    public function test_rejects_installment_plan_for_default_cash_customer()
    {
        $cashCustomer = $this->company->getOrCreateDefaultCashCustomer();
        $invoiceType = InvoiceType::factory()->create(['code' => 'sale']);
        $this->company->invoiceTypes()->syncWithoutDetaching([$invoiceType->id => ['is_active' => true]]);

        $payload = [
            'invoice_type_id' => $invoiceType->id,
            'invoice_type_code' => 'sale',
            'gross_amount' => 1000,
            'net_amount' => 1000,
            'paid_amount' => 1000,
            'remaining_amount' => 0,
            'user_id' => $cashCustomer->id,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'installment_plan' => [ // يجب رفض هذا للعميل النقدي
                'down_payment' => 200,
                'number_of_installments' => 4,
                'frequency' => 'monthly',
            ],
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => $this->product->name,
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'discount' => 0,
                    'total' => 1000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['installment_plan']);
    }

    /**
     * اختبار: إنشاء فاتورة بيع بالتقسيط بنجاح مع إنشاء خطة التقسيط والأقساط المجدولة.
     */
    public function test_resolves_invoice_creation_service_for_installment_sale()
    {
        $invoiceType = InvoiceType::factory()->create([
            'code' => 'installment_sale',
            'company_id' => $this->company->id
        ]);

        $customer = User::factory()->create();
        // Link customer to company (Membership)
        \App\Models\CompanyUser::create([
            'user_id' => $customer->id,
            'company_id' => $this->company->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'user_id' => $customer->id,
            'invoice_type_id' => $invoiceType->id,
            'invoice_type_code' => 'installment_sale',
            'invoice_number' => 'INV-INST-001',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'gross_amount' => 12000,
            'net_amount' => 12000,
            'paid_amount' => 2000,
            'remaining_amount' => 10000,
            'total_balance' => 12000,
            'status' => 'confirmed',
            'company_id' => $this->company->id,
            'cash_box_id' => $this->cashBox->id,
            'installment_plan' => [
                'down_payment' => 2000,
                'number_of_installments' => 10,
                'installment_amount' => 1000,
                'frequency' => 'monthly',
                'net_amount' => 12000,
                'interest_rate' => 0,
                'interest_amount' => 0,
                'total_amount' => 12000,
                'round_step' => 5,
                'start_date' => now()->addMonth()->format('Y-m-d'),
            ],
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => $this->product->name,
                    'quantity' => 12,
                    'unit_price' => 1000,
                    'discount' => 0,
                    'total' => 12000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['invoice_type_id' => $invoiceType->id]);
        $this->assertDatabaseHas('installment_plans', [
            'invoice_id' => $response->json('data.id'),
            'user_id' => $customer->id,
            'remaining_amount' => 10000.00
        ]);
        $this->assertDatabaseHas('installments', [
            'user_id' => $customer->id,
            'amount' => 1000.00
        ]);
    }
}
