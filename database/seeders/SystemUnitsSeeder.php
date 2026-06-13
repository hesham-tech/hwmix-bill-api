<?php
// كلاس تهيئة وحدات القياس والمجموعات وتحويلات السيستم الأساسية الافتراضية
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitConversion;

class SystemUnitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. تعريف مجموعات القياس
        $groupsData = [
            ['name' => 'وحدات العد والتعبئة', 'type' => 'count'],
            ['name' => 'وحدات الوزن والكتلة', 'type' => 'weight'],
            ['name' => 'وحدات الطول والمسافة', 'type' => 'length'],
            ['name' => 'وحدات الحجم والسعة', 'type' => 'volume'],
            ['name' => 'وحدات المساحة', 'type' => 'area'],
        ];

        $groups = [];
        foreach ($groupsData as $gData) {
            $groups[$gData['type']] = UnitGroup::firstOrCreate(
                ['type' => $gData['type']],
                [
                    'name' => $gData['name'],
                    'company_id' => null, // عامة للسيستم بالكامل
                    'created_by' => null,
                ]
            );
        }

        // 2. تعريف الوحدات الفردية
        $unitsData = [
            // مجموعة العد
            'count' => [
                ['name' => 'قطعة', 'code' => 'pcs', 'decimal_places' => 0],
                ['name' => 'كرتونة', 'code' => 'box', 'decimal_places' => 0],
                ['name' => 'باكيت', 'code' => 'pkt', 'decimal_places' => 0],
                ['name' => 'لفة', 'code' => 'roll', 'decimal_places' => 2],
            ],
            // مجموعة الوزن
            'weight' => [
                ['name' => 'جرام', 'code' => 'g', 'decimal_places' => 3],
                ['name' => 'كيلوجرام', 'code' => 'kg', 'decimal_places' => 3],
                ['name' => 'طن', 'code' => 'ton', 'decimal_places' => 3],
            ],
            // مجموعة الطول
            'length' => [
                ['name' => 'سنتيمتر', 'code' => 'cm', 'decimal_places' => 2],
                ['name' => 'متر', 'code' => 'm', 'decimal_places' => 2],
            ],
            // مجموعة الحجم
            'volume' => [
                ['name' => 'ملليلتر', 'code' => 'ml', 'decimal_places' => 3],
                ['name' => 'لتر', 'code' => 'L', 'decimal_places' => 3],
            ],
            // مجموعة المساحة
            'area' => [
                ['name' => 'متر مربع', 'code' => 'm2', 'decimal_places' => 2],
            ]
        ];

        $units = [];
        foreach ($unitsData as $type => $uList) {
            $groupId = $groups[$type]->id;
            foreach ($uList as $uData) {
                $units[$uData['code']] = Unit::firstOrCreate(
                    [
                        'unit_group_id' => $groupId,
                        'code' => $uData['code'],
                    ],
                    [
                        'name' => $uData['name'],
                        'decimal_places' => $uData['decimal_places'],
                        'is_active' => true,
                        'company_id' => null,
                        'created_by' => null,
                    ]
                );
            }
        }

        // 3. تعريف التحويلات القياسية
        $conversionsData = [
            // كيلوجرام <-> جرام (المجموعة: weight)
            [
                'type' => 'weight',
                'from' => 'kg',
                'to' => 'g',
                'factor' => 1000.000000,
                'reverse' => 0.001000
            ],
            // طن <-> كيلوجرام (المجموعة: weight)
            [
                'type' => 'weight',
                'from' => 'ton',
                'to' => 'kg',
                'factor' => 1000.000000,
                'reverse' => 0.001000
            ],
            // متر <-> سنتيمتر (المجموعة: length)
            [
                'type' => 'length',
                'from' => 'm',
                'to' => 'cm',
                'factor' => 100.000000,
                'reverse' => 0.010000
            ],
            // لتر <-> ملليلتر (المجموعة: volume)
            [
                'type' => 'volume',
                'from' => 'L',
                'to' => 'ml',
                'factor' => 1000.000000,
                'reverse' => 0.001000
            ]
        ];

        foreach ($conversionsData as $cData) {
            $groupId = $groups[$cData['type']]->id;
            $fromUnitId = $units[$cData['from']]->id;
            $toUnitId = $units[$cData['to']]->id;

            UnitConversion::firstOrCreate(
                [
                    'unit_group_id' => $groupId,
                    'from_unit_id' => $fromUnitId,
                    'to_unit_id' => $toUnitId,
                ],
                [
                    'factor' => $cData['factor'],
                    'reverse_factor' => $cData['reverse'],
                    'company_id' => null,
                    'created_by' => null,
                ]
            );
        }

        // تحديث المنتجات والمتغيرات القديمة للتوافقية الرجعية
        $pcsUnit = $units['pcs'] ?? null;
        if ($pcsUnit) {
            \Illuminate\Support\Facades\DB::table('products')
                ->whereNull('base_unit_id')
                ->update([
                    'base_unit_id' => $pcsUnit->id,
                    'purchase_unit_id' => $pcsUnit->id,
                    'display_unit_id' => $pcsUnit->id,
                ]);

            \Illuminate\Support\Facades\DB::table('product_variants')
                ->whereNull('base_unit_id')
                ->update([
                    'base_unit_id' => $pcsUnit->id,
                    'purchase_unit_id' => $pcsUnit->id,
                    'display_unit_id' => $pcsUnit->id,
                ]);
        }
    }
}
