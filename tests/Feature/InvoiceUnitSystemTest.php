<?php
// اختبارات تكامل للتحقق من تكامل نظام وحدات القياس مع المخازن والفواتير والخصم التلقائي
namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitConversion;
use Modules\Inventory\Models\ProductVariantUnit;
use Modules\Inventory\Models\ProductVariantUnitPrice;
use Modules\Inventory\Models\Warehouse;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceItem;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceUnitSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Warehouse $warehouse;
    protected UnitGroup $weightGroup;
    protected Unit $gUnit;
    protected Unit $kgUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->admin->givePermissionTo('admin.super');

        // إنشاء خزنة افتراضية للأدمن
        \App\Models\CashBox::factory()->create([
            'user_id' => $this->admin->id,
            'company_id' => $this->company->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

        // إعداد مجموعات ووحدات القياس
        $this->weightGroup = UnitGroup::create([
            'name' => 'الوزن',
            'type' => 'weight',
            'company_id' => $this->company->id,
        ]);

        $this->gUnit = Unit::create([
            'unit_group_id' => $this->weightGroup->id,
            'name' => 'جرام',
            'code' => 'g',
            'decimal_places' => 3,
            'company_id' => $this->company->id,
        ]);

        $this->kgUnit = Unit::create([
            'unit_group_id' => $this->weightGroup->id,
            'name' => 'كيلوجرام',
            'code' => 'kg',
            'decimal_places' => 3,
            'company_id' => $this->company->id,
        ]);

        // تحويل عالمي: 1 كجم = 1000 جرام
        UnitConversion::create([
            'unit_group_id' => $this->weightGroup->id,
            'from_unit_id' => $this->kgUnit->id,
            'to_unit_id' => $this->gUnit->id,
            'factor' => 1000.0,
            'reverse_factor' => 0.001,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * اختبار بيع منتج بوحدة غير الوحدة الأساسية والتأكد من خصم المخزون بالوحدة الأساسية.
     */
    public function test_sale_invoice_deducts_stock_in_base_unit()
    {
        $this->actingAs($this->admin);

        $product = Product::create([
            'name' => 'حلاوة طحينية',
            'slug' => 'halawa-tahineya',
            'product_type' => 'physical',
            'require_stock' => true,
            'category_id' => \Modules\Inventory\Models\Category::factory()->create(['company_id' => $this->company->id])->id,
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'base_unit_id' => $this->gUnit->id,
            'display_unit_id' => $this->kgUnit->id,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'HALAWA-001',
            'retail_price' => 10.0,
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'base_unit_id' => $this->gUnit->id,
            'display_unit_id' => $this->kgUnit->id,
        ]);

        // ربط الكيلوجرام بالمتغير بمعامل تحويل 1000
        ProductVariantUnit::create([
            'product_variant_id' => $variant->id,
            'unit_id' => $this->kgUnit->id,
            'conversion_factor_to_base' => 1000.0,
            'is_default' => true,
        ]);

        // إضافة مخزون بالوحدة الأساسية (5000 جرام = 5 كجم)
        Stock::create([
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5000,
            'status' => 'available',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        // إنشاء عميل وخزنة له
        $customer = User::factory()->create(['company_id' => $this->company->id]);
        \App\Models\CashBox::factory()->create([
            'user_id' => $customer->id,
            'company_id' => $this->company->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        // بيع 2 كجم
        $payload = [
            'invoice_type_id' => \Modules\Sales\Models\InvoiceType::firstOrCreate(['code' => 'sale', 'name' => 'بيع'])->id,
            'gross_amount' => 100.0,
            'net_amount' => 100.0,
            'paid_amount' => 100.0,
            'user_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'name' => $product->name,
                    'quantity' => 2, // 2 كجم
                    'unit_id' => $this->kgUnit->id,
                    'unit_price' => 50.0,
                    'total' => 100.0,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);

        // التحقق من لقطة بند الفاتورة
        $item = InvoiceItem::first();
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals($this->kgUnit->id, $item->unit_id);
        $this->assertEquals(1000.0, $item->conversion_factor_snapshot);
        $this->assertEquals(2000.0, $item->base_quantity); // 2 كجم = 2000 جرام

        // التحقق من خصم المخزون بالوحدة الأساسية (المتبقي: 5000 - 2000 = 3000 جرام)
        $stock = Stock::where('variant_id', $variant->id)->where('status', 'available')->first();
        $this->assertEquals(3000.0, $stock->quantity);
    }
}
