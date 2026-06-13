<?php
// اختبارات وحدة لخدمة تحويل وحدات القياس للتأكد من دقة التحويلات الرياضية والقيود المتبعة
namespace Tests\Unit;

use Tests\TestCase;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitConversion;
use Modules\Inventory\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Exception;

class UnitConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private UnitConversionService $service;
    private UnitGroup $weightGroup;
    private UnitGroup $lengthGroup;
    private Unit $kg;
    private Unit $g;
    private Unit $ton;
    private Unit $meter;
    private Unit $cm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnitConversionService();

        // إنشاء المجموعات
        $this->weightGroup = UnitGroup::create([
            'name' => 'الوزن',
            'type' => 'weight',
        ]);

        $this->lengthGroup = UnitGroup::create([
            'name' => 'الطول',
            'type' => 'length',
        ]);

        // إنشاء الوحدات
        $this->g = Unit::create([
            'unit_group_id' => $this->weightGroup->id,
            'name' => 'جرام',
            'code' => 'g',
            'decimal_places' => 3,
        ]);

        $this->kg = Unit::create([
            'unit_group_id' => $this->weightGroup->id,
            'name' => 'كيلوجرام',
            'code' => 'kg',
            'decimal_places' => 3,
        ]);

        $this->ton = Unit::create([
            'unit_group_id' => $this->weightGroup->id,
            'name' => 'طن',
            'code' => 'ton',
            'decimal_places' => 3,
        ]);

        $this->cm = Unit::create([
            'unit_group_id' => $this->lengthGroup->id,
            'name' => 'سنتيمتر',
            'code' => 'cm',
            'decimal_places' => 2,
        ]);

        $this->meter = Unit::create([
            'unit_group_id' => $this->lengthGroup->id,
            'name' => 'متر',
            'code' => 'm',
            'decimal_places' => 2,
        ]);
    }

    /**
     * اختبار التحويل من الوحدة لنفسها يرجع نفس الكمية.
     */
    public function test_conversion_to_same_unit_returns_same_quantity()
    {
        $result = $this->service->convert(15.5, $this->kg->id, $this->kg->id);
        $this->assertEquals(15.5, $result);
    }

    /**
     * اختبار تحويل مباشر باستخدام المعامل.
     */
    public function test_direct_conversion()
    {
        // 1 كجم = 1000 جرام
        UnitConversion::create([
            'unit_group_id' => $this->weightGroup->id,
            'from_unit_id' => $this->kg->id,
            'to_unit_id' => $this->g->id,
            'factor' => 1000.0,
            'reverse_factor' => 0.001,
        ]);

        $result = $this->service->convert(2.5, $this->kg->id, $this->g->id);
        $this->assertEquals(2500.0, $result);
    }

    /**
     * اختبار تحويل مباشر عكسي.
     */
    public function test_reverse_direct_conversion()
    {
        // 1 كجم = 1000 جرام
        UnitConversion::create([
            'unit_group_id' => $this->weightGroup->id,
            'from_unit_id' => $this->kg->id,
            'to_unit_id' => $this->g->id,
            'factor' => 1000.0,
            'reverse_factor' => 0.001,
        ]);

        // من جرام إلى كجم (قسمة على 1000)
        $result = $this->service->convert(500.0, $this->g->id, $this->kg->id);
        $this->assertEquals(0.5, $result);
    }

    /**
     * اختبار تحويل غير مباشر عبر Pivot Unit وسيطة.
     */
    public function test_indirect_conversion_via_pivot()
    {
        // طن <-> كجم
        UnitConversion::create([
            'unit_group_id' => $this->weightGroup->id,
            'from_unit_id' => $this->ton->id,
            'to_unit_id' => $this->kg->id,
            'factor' => 1000.0,
            'reverse_factor' => 0.001,
        ]);

        // كجم <-> جرام
        UnitConversion::create([
            'unit_group_id' => $this->weightGroup->id,
            'from_unit_id' => $this->kg->id,
            'to_unit_id' => $this->g->id,
            'factor' => 1000.0,
            'reverse_factor' => 0.001,
        ]);

        // تحويل من طن إلى جرام (الوسيط هو كجم)
        // 0.5 طن = 500 كجم = 500,000 جرام
        $result = $this->service->convert(0.5, $this->ton->id, $this->g->id);
        $this->assertEquals(500000.0, $result);
    }

    /**
     * اختبار منع التحويل بين مجموعات مختلفة.
     */
    public function test_conversion_between_different_groups_throws_exception()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('لا يمكن التحويل بين وحدات من مجموعات قياس مختلفة');

        $this->service->convert(10.0, $this->kg->id, $this->meter->id);
    }

    /**
     * اختبار رمي استثناء عند عدم وجود مسار تحويل.
     */
    public function test_no_conversion_path_throws_exception()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('لا توجد قاعدة تحويل معرفة');

        // لا توجد تحويلات معرفة للوزن
        $this->service->convert(10.0, $this->kg->id, $this->ton->id);
    }
}
