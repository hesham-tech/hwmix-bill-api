<?php
// كلاس تهيئة وحدات القياس والمجموعات وتحويلات السيستم الأساسية الافتراضية الشاملة
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitConversion;

class SystemUnitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * جميع السجلات تستخدم firstOrCreate لضمان الأمان عند التشغيل المتكرر
     */
    public function run(): void
    {
        // ═══════════════════════════════════════════════════════
        // 1. تعريف مجموعات القياس
        // ═══════════════════════════════════════════════════════
        $groupsData = [
            ['name' => 'وحدات العد والتعبئة',      'type' => 'count'],
            ['name' => 'وحدات الوزن والكتلة',      'type' => 'weight'],
            ['name' => 'وحدات الطول والمسافة',     'type' => 'length'],
            ['name' => 'وحدات الحجم والسعة',       'type' => 'volume'],
            ['name' => 'وحدات المساحة',             'type' => 'area'],
            ['name' => 'وحدات الزمن',              'type' => 'time'],
            ['name' => 'وحدات التعبئة والتغليف',   'type' => 'packaging'],
        ];

        $groups = [];
        foreach ($groupsData as $gData) {
            $groups[$gData['type']] = UnitGroup::firstOrCreate(
                ['type' => $gData['type'], 'company_id' => null],
                [
                    'name'       => $gData['name'],
                    'created_by' => null,
                ]
            );
        }

        // ═══════════════════════════════════════════════════════
        // 2. تعريف الوحدات الفردية لكل مجموعة
        // ═══════════════════════════════════════════════════════
        $unitsData = [

            // ───── مجموعة العد والتعبئة ─────
            'count' => [
                ['name' => 'قطعة',    'code' => 'pcs',    'decimal_places' => 0],
                ['name' => 'دزينة',   'code' => 'doz',    'decimal_places' => 0],
                ['name' => 'زوج',     'code' => 'pr',     'decimal_places' => 0],
                ['name' => 'كرتونة',  'code' => 'box',    'decimal_places' => 0],
                ['name' => 'باكيت',   'code' => 'pkt',    'decimal_places' => 0],
                ['name' => 'لفة',     'code' => 'roll',   'decimal_places' => 2],
                ['name' => 'رزمة',    'code' => 'bndl',   'decimal_places' => 0],
                ['name' => 'طرد',     'code' => 'prcl',   'decimal_places' => 0],
            ],

            // ───── مجموعة الوزن والكتلة ─────
            'weight' => [
                ['name' => 'ميليجرام',  'code' => 'mg',  'decimal_places' => 3],
                ['name' => 'جرام',      'code' => 'g',   'decimal_places' => 3],
                ['name' => 'كيلوجرام',  'code' => 'kg',  'decimal_places' => 3],
                ['name' => 'طن',        'code' => 'ton', 'decimal_places' => 3],
                ['name' => 'رطل',       'code' => 'lb',  'decimal_places' => 3],
                ['name' => 'أوقية',     'code' => 'oz',  'decimal_places' => 3],
            ],

            // ───── مجموعة الطول والمسافة ─────
            'length' => [
                ['name' => 'ملليمتر',   'code' => 'mm',  'decimal_places' => 2],
                ['name' => 'سنتيمتر',   'code' => 'cm',  'decimal_places' => 2],
                ['name' => 'متر',        'code' => 'm',   'decimal_places' => 2],
                ['name' => 'كيلومتر',   'code' => 'km',  'decimal_places' => 3],
                ['name' => 'بوصة',      'code' => 'in',  'decimal_places' => 2],
                ['name' => 'قدم',       'code' => 'ft',  'decimal_places' => 2],
                ['name' => 'يارد',      'code' => 'yd',  'decimal_places' => 2],
            ],

            // ───── مجموعة الحجم والسعة ─────
            'volume' => [
                ['name' => 'ملليلتر',   'code' => 'ml',  'decimal_places' => 3],
                ['name' => 'سنتيلتر',   'code' => 'cl',  'decimal_places' => 3],
                ['name' => 'ديسيلتر',   'code' => 'dl',  'decimal_places' => 3],
                ['name' => 'لتر',        'code' => 'L',   'decimal_places' => 3],
                ['name' => 'متر مكعب',  'code' => 'm3',  'decimal_places' => 3],
                ['name' => 'غالون',     'code' => 'gal', 'decimal_places' => 3],
            ],

            // ───── مجموعة المساحة ─────
            'area' => [
                ['name' => 'سنتيمتر مربع',  'code' => 'cm2', 'decimal_places' => 2],
                ['name' => 'متر مربع',      'code' => 'm2',  'decimal_places' => 2],
                ['name' => 'هكتار',          'code' => 'ha',  'decimal_places' => 4],
                ['name' => 'فدان',           'code' => 'fd',  'decimal_places' => 4],
                ['name' => 'كيلومتر مربع',  'code' => 'km2', 'decimal_places' => 6],
            ],

            // ───── مجموعة الزمن ─────
            'time' => [
                ['name' => 'دقيقة',  'code' => 'min',   'decimal_places' => 0],
                ['name' => 'ساعة',   'code' => 'hr',    'decimal_places' => 2],
                ['name' => 'يوم',    'code' => 'day',   'decimal_places' => 2],
                ['name' => 'أسبوع',  'code' => 'wk',    'decimal_places' => 2],
                ['name' => 'شهر',    'code' => 'mon',   'decimal_places' => 2],
                ['name' => 'سنة',    'code' => 'yr',    'decimal_places' => 2],
            ],

            // ───── مجموعة التعبئة والتغليف ─────
            'packaging' => [
                ['name' => 'علبة',    'code' => 'can',    'decimal_places' => 0],
                ['name' => 'زجاجة',   'code' => 'btle',   'decimal_places' => 0],
                ['name' => 'كيس',     'code' => 'bag',    'decimal_places' => 0],
                ['name' => 'علبة كرتون',  'code' => 'ctn',  'decimal_places' => 0],
                ['name' => 'براميل',  'code' => 'brl',    'decimal_places' => 2],
                ['name' => 'صندوق',   'code' => 'crate',  'decimal_places' => 0],
                ['name' => 'شريط',    'code' => 'strip',  'decimal_places' => 0],
                ['name' => 'أمبولة',  'code' => 'amp',    'decimal_places' => 0],
            ],
        ];

        $units = [];
        foreach ($unitsData as $type => $uList) {
            $groupId = $groups[$type]->id;
            foreach ($uList as $uData) {
                $units[$uData['code']] = Unit::firstOrCreate(
                    [
                        'unit_group_id' => $groupId,
                        'code'          => $uData['code'],
                        'company_id'    => null,
                    ],
                    [
                        'name'           => $uData['name'],
                        'decimal_places' => $uData['decimal_places'],
                        'is_active'      => true,
                        'created_by'     => null,
                    ]
                );
            }
        }

        // ═══════════════════════════════════════════════════════
        // 3. تعريف تحويلات القياس القياسية
        // الصيغة: 1 (from_unit) = factor × (to_unit)
        // ═══════════════════════════════════════════════════════
        $conversionsData = [

            // ─── تحويلات الوزن ───
            ['type' => 'weight', 'from' => 'mg',  'to' => 'g',   'factor' => 0.001,        'reverse' => 1000.0],
            ['type' => 'weight', 'from' => 'g',   'to' => 'kg',  'factor' => 0.001,        'reverse' => 1000.0],
            ['type' => 'weight', 'from' => 'kg',  'to' => 'g',   'factor' => 1000.0,       'reverse' => 0.001],
            ['type' => 'weight', 'from' => 'kg',  'to' => 'ton', 'factor' => 0.001,        'reverse' => 1000.0],
            ['type' => 'weight', 'from' => 'ton', 'to' => 'kg',  'factor' => 1000.0,       'reverse' => 0.001],
            ['type' => 'weight', 'from' => 'lb',  'to' => 'g',   'factor' => 453.592,      'reverse' => 0.0022046],
            ['type' => 'weight', 'from' => 'lb',  'to' => 'kg',  'factor' => 0.453592,     'reverse' => 2.20462],
            ['type' => 'weight', 'from' => 'oz',  'to' => 'g',   'factor' => 28.3495,      'reverse' => 0.035274],

            // ─── تحويلات الطول ───
            ['type' => 'length', 'from' => 'mm',  'to' => 'cm',  'factor' => 0.1,          'reverse' => 10.0],
            ['type' => 'length', 'from' => 'cm',  'to' => 'mm',  'factor' => 10.0,         'reverse' => 0.1],
            ['type' => 'length', 'from' => 'cm',  'to' => 'm',   'factor' => 0.01,         'reverse' => 100.0],
            ['type' => 'length', 'from' => 'm',   'to' => 'cm',  'factor' => 100.0,        'reverse' => 0.01],
            ['type' => 'length', 'from' => 'm',   'to' => 'km',  'factor' => 0.001,        'reverse' => 1000.0],
            ['type' => 'length', 'from' => 'km',  'to' => 'm',   'factor' => 1000.0,       'reverse' => 0.001],
            ['type' => 'length', 'from' => 'in',  'to' => 'cm',  'factor' => 2.54,         'reverse' => 0.393701],
            ['type' => 'length', 'from' => 'ft',  'to' => 'cm',  'factor' => 30.48,        'reverse' => 0.0328084],
            ['type' => 'length', 'from' => 'ft',  'to' => 'm',   'factor' => 0.3048,       'reverse' => 3.28084],
            ['type' => 'length', 'from' => 'yd',  'to' => 'm',   'factor' => 0.9144,       'reverse' => 1.09361],
            ['type' => 'length', 'from' => 'yd',  'to' => 'ft',  'factor' => 3.0,          'reverse' => 0.333333],

            // ─── تحويلات الحجم ───
            ['type' => 'volume', 'from' => 'ml',  'to' => 'cl',  'factor' => 0.1,          'reverse' => 10.0],
            ['type' => 'volume', 'from' => 'ml',  'to' => 'dl',  'factor' => 0.01,         'reverse' => 100.0],
            ['type' => 'volume', 'from' => 'ml',  'to' => 'L',   'factor' => 0.001,        'reverse' => 1000.0],
            ['type' => 'volume', 'from' => 'cl',  'to' => 'ml',  'factor' => 10.0,         'reverse' => 0.1],
            ['type' => 'volume', 'from' => 'dl',  'to' => 'ml',  'factor' => 100.0,        'reverse' => 0.01],
            ['type' => 'volume', 'from' => 'L',   'to' => 'ml',  'factor' => 1000.0,       'reverse' => 0.001],
            ['type' => 'volume', 'from' => 'm3',  'to' => 'L',   'factor' => 1000.0,       'reverse' => 0.001],
            ['type' => 'volume', 'from' => 'gal', 'to' => 'L',   'factor' => 3.78541,      'reverse' => 0.264172],
            ['type' => 'volume', 'from' => 'gal', 'to' => 'ml',  'factor' => 3785.41,      'reverse' => 0.000264172],

            // ─── تحويلات المساحة ───
            ['type' => 'area', 'from' => 'cm2',   'to' => 'm2',  'factor' => 0.0001,       'reverse' => 10000.0],
            ['type' => 'area', 'from' => 'm2',    'to' => 'cm2', 'factor' => 10000.0,      'reverse' => 0.0001],
            ['type' => 'area', 'from' => 'm2',    'to' => 'km2', 'factor' => 0.000001,     'reverse' => 1000000.0],
            ['type' => 'area', 'from' => 'km2',   'to' => 'm2',  'factor' => 1000000.0,    'reverse' => 0.000001],
            ['type' => 'area', 'from' => 'ha',    'to' => 'm2',  'factor' => 10000.0,      'reverse' => 0.0001],
            ['type' => 'area', 'from' => 'fd',    'to' => 'm2',  'factor' => 4200.0,       'reverse' => 0.000238095], // الفدان المصري
            ['type' => 'area', 'from' => 'fd',    'to' => 'ha',  'factor' => 0.42,         'reverse' => 2.38095],

            // ─── تحويلات الزمن ───
            ['type' => 'time', 'from' => 'min',   'to' => 'hr',  'factor' => 0.016667,     'reverse' => 60.0],
            ['type' => 'time', 'from' => 'hr',    'to' => 'min', 'factor' => 60.0,         'reverse' => 0.016667],
            ['type' => 'time', 'from' => 'hr',    'to' => 'day', 'factor' => 0.041667,     'reverse' => 24.0],
            ['type' => 'time', 'from' => 'day',   'to' => 'hr',  'factor' => 24.0,         'reverse' => 0.041667],
            ['type' => 'time', 'from' => 'day',   'to' => 'wk',  'factor' => 0.142857,     'reverse' => 7.0],
            ['type' => 'time', 'from' => 'wk',    'to' => 'day', 'factor' => 7.0,          'reverse' => 0.142857],
            ['type' => 'time', 'from' => 'mon',   'to' => 'day', 'factor' => 30.0,         'reverse' => 0.033333],
            ['type' => 'time', 'from' => 'mon',   'to' => 'wk',  'factor' => 4.34524,      'reverse' => 0.230137],
            ['type' => 'time', 'from' => 'yr',    'to' => 'mon', 'factor' => 12.0,         'reverse' => 0.083333],
            ['type' => 'time', 'from' => 'yr',    'to' => 'day', 'factor' => 365.0,        'reverse' => 0.002740],
            ['type' => 'time', 'from' => 'yr',    'to' => 'wk',  'factor' => 52.1429,      'reverse' => 0.019178],

            // ─── تحويلات العد (وحدات قابلة للتحويل) ───
            ['type' => 'count', 'from' => 'doz',  'to' => 'pcs', 'factor' => 12.0,         'reverse' => 0.083333],
            ['type' => 'count', 'from' => 'pr',   'to' => 'pcs', 'factor' => 2.0,          'reverse' => 0.5],
        ];

        foreach ($conversionsData as $cData) {
            if (!isset($units[$cData['from']]) || !isset($units[$cData['to']])) {
                continue; // تجاوز في حال وجود خطأ في الكود
            }

            $groupId    = $groups[$cData['type']]->id;
            $fromUnitId = $units[$cData['from']]->id;
            $toUnitId   = $units[$cData['to']]->id;

            UnitConversion::firstOrCreate(
                [
                    'unit_group_id' => $groupId,
                    'from_unit_id'  => $fromUnitId,
                    'to_unit_id'    => $toUnitId,
                ],
                [
                    'factor'         => $cData['factor'],
                    'reverse_factor' => $cData['reverse'],
                    'company_id'     => null,
                    'created_by'     => null,
                ]
            );
        }

        // ═══════════════════════════════════════════════════════
        // 4. تحديث المنتجات والمتغيرات القديمة للتوافقية الرجعية
        // ═══════════════════════════════════════════════════════
        $pcsUnit = $units['pcs'] ?? null;
        if ($pcsUnit) {
            \Illuminate\Support\Facades\DB::table('products')
                ->whereNull('base_unit_id')
                ->update([
                    'base_unit_id'     => $pcsUnit->id,
                    'purchase_unit_id' => $pcsUnit->id,
                    'display_unit_id'  => $pcsUnit->id,
                ]);

            \Illuminate\Support\Facades\DB::table('product_variants')
                ->whereNull('base_unit_id')
                ->update([
                    'base_unit_id'     => $pcsUnit->id,
                    'purchase_unit_id' => $pcsUnit->id,
                    'display_unit_id'  => $pcsUnit->id,
                ]);
        }
    }
}
