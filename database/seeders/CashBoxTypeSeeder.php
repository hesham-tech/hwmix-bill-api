<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashBoxType;

class CashBoxTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // الأساسية
            [
                'name' => 'نقدي',
                'description' => 'صندوق نقدي - للاحتفاظ بالنقود السائلة',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'حساب بنكي',
                'description' => 'حساب بنكي - للتحويلات والمعاملات البنكية',
                'is_system' => true,
                'is_active' => true,
            ],

            // المحافظ الإلكترونية المصرية
            [
                'name' => 'فودافون كاش',
                'description' => 'محفظة فودافون كاش الإلكترونية',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'اتصالات كاش',
                'description' => 'محفظة اتصالات كاش الإلكترونية',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'أورنج كاش',
                'description' => 'محفظة أورنج كاش الإلكترونية',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'إنستاباي',
                'description' => 'حساب إنستاباي للتحويلات الفورية',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'فوري',
                'description' => 'حساب فوري للدفع الإلكتروني',
                'is_system' => true,
                'is_active' => true,
            ],

            // خدمات الدفع الإلكتروني
            [
                'name' => 'باي بال',
                'description' => 'حساب باي بال للمدفوعات الدولية',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'بطاقة ائتمان',
                'description' => 'حساب مخصص لمدفوعات البطاقات الائتمانية',
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            CashBoxType::firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
