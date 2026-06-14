<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\InvoiceType;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;

// اختبارات للتحقق من سلامة عملية التحويل المخزني بين المستودعات وتوافق الصلاحيات والعزل
class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $product;
    protected $variant;
    protected $warehouseSource;
    protected $warehouseDestination;
    protected $transferType;

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

        $this->actingAs($this->user);

        // Ensure stock_transfer invoice type exists
        $this->transferType = InvoiceType::firstOrCreate(
            ['code' => 'stock_transfer'],
            [
                'name' => 'تحويل مخزني',
                'description' => 'تحويل مخزني بين المستودعات',
                'module' => 'inventory',
                'is_active' => true,
                'created_by' => 1
            ]
        );

        $this->product = Product::factory()->create(['company_id' => $this->company->id]);
        $this->variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'company_id' => $this->company->id
        ]);

        $this->warehouseSource = Warehouse::factory()->create(['company_id' => $this->company->id]);
        $this->warehouseDestination = Warehouse::factory()->create(['company_id' => $this->company->id]);

        // Setup 10 items in source warehouse
        Stock::factory()->create([
            'variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouseSource->id,
            'company_id' => $this->company->id,
            'quantity' => 10,
            'status' => 'available',
            'cost' => 50.00,
            'batch' => 'BATCH-TEST-123'
        ]);
    }

    /**
     * اختبار نجاح عملية التحويل المخزني
     */
    public function test_can_transfer_stock_successfully()
    {
        $payload = [
            'invoice_type_id' => $this->transferType->id,
            'invoice_type_code' => 'stock_transfer',
            'warehouse_id' => $this->warehouseSource->id,
            'to_warehouse_id' => $this->warehouseDestination->id,
            'gross_amount' => 0,
            'net_amount' => 0,
            'user_id' => $this->user->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => 'Test Product',
                    'quantity' => 4,
                    'unit_price' => 0,
                    'total' => 0
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);
        
        // التحقق من صحة تقليص كمية المصدر (10 - 4 = 6)
        $this->assertEquals(6, Stock::where('variant_id', $this->variant->id)
            ->where('warehouse_id', $this->warehouseSource->id)
            ->sum('quantity'));

        // التحقق من صحة إضافة الكمية في المستهدف (4) بنفس التكلفة والدفعة
        $destStock = Stock::where('variant_id', $this->variant->id)
            ->where('warehouse_id', $this->warehouseDestination->id)
            ->first();

        $this->assertNotNull($destStock);
        $this->assertEquals(4, $destStock->quantity);
        $this->assertEquals(50.00, $destStock->cost);
        $this->assertEquals('BATCH-TEST-123', $destStock->batch);
    }

    /**
     * اختبار فشل التحويل عند عدم كفاية المخزون في المصدر
     */
    public function test_cannot_transfer_stock_with_insufficient_quantity()
    {
        $payload = [
            'invoice_type_id' => $this->transferType->id,
            'invoice_type_code' => 'stock_transfer',
            'warehouse_id' => $this->warehouseSource->id,
            'to_warehouse_id' => $this->warehouseDestination->id,
            'gross_amount' => 0,
            'net_amount' => 0,
            'user_id' => $this->user->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => 'Test Product',
                    'quantity' => 15, // أكبر من المتاح (10)
                    'unit_price' => 0,
                    'total' => 0
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items.0.quantity');
    }

    /**
     * اختبار فشل التحويل لنفس المستودع
     */
    public function test_cannot_transfer_stock_to_same_warehouse()
    {
        $payload = [
            'invoice_type_id' => $this->transferType->id,
            'invoice_type_code' => 'stock_transfer',
            'warehouse_id' => $this->warehouseSource->id,
            'to_warehouse_id' => $this->warehouseSource->id, // نفس المستودع
            'gross_amount' => 0,
            'net_amount' => 0,
            'user_id' => $this->user->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => 'Test Product',
                    'quantity' => 2,
                    'unit_price' => 0,
                    'total' => 0
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('to_warehouse_id');
    }

    /**
     * اختبار عزل الشركات متعددة المستأجرين (Multi-tenant Isolation)
     */
    public function test_multi_tenant_isolation_prevents_unauthorized_warehouse_transfer()
    {
        // إنشاء شركة أخرى مستقلة ومستودع يتبعها
        $otherCompany = Company::factory()->create();
        $otherWarehouse = Warehouse::factory()->create(['company_id' => $otherCompany->id]);

        $payload = [
            'invoice_type_id' => $this->transferType->id,
            'invoice_type_code' => 'stock_transfer',
            'warehouse_id' => $this->warehouseSource->id,
            'to_warehouse_id' => $otherWarehouse->id, // مستودع شركة أخرى!
            'gross_amount' => 0,
            'net_amount' => 0,
            'user_id' => $this->user->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 0,
                    'total' => 0
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        // يجب أن يرجع خطأ تحقق 422 لأن المستودع غير موجود بنطاق الشركة النشطة
        $response->assertStatus(422);
    }
}
