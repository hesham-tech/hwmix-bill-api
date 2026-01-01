<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            // الطرق الأساسية الأكثر استخداماً
            [
                'name' => 'نقدي',
                'code' => 'cash',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'آجل',
                'code' => 'credit',
                'active' => true,
                'is_system' => true,
            ],

            // البطاقات والحسابات البنكية
            [
                'name' => 'بطاقة ائتمان',
                'code' => 'credit_card',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'بطاقة خصم مباشر',
                'code' => 'debit_card',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'تحويل بنكي',
                'code' => 'bank_transfer',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'شيك',
                'code' => 'check',
                'active' => true,
                'is_system' => true,
            ],

            // المحافظ الإلكترونية المصرية
            [
                'name' => 'فودافون كاش',
                'code' => 'vodafone_cash',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'اتصالات كاش',
                'code' => 'etisalat_cash',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'أورنج كاش',
                'code' => 'orange_cash',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'إنستاباي',
                'code' => 'instapay',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'فوري',
                'code' => 'fawry',
                'active' => true,
                'is_system' => true,
            ],

            // خدمات الدفع الإلكتروني
            [
                'name' => 'باي بال',
                'code' => 'paypal',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'فيزا أونلاين',
                'code' => 'visa_online',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'ماستركارد أونلاين',
                'code' => 'mastercard_online',
                'active' => true,
                'is_system' => true,
            ],

            // التقسيط
            [
                'name' => 'تقسيط',
                'code' => 'installment',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'فاليو',
                'code' => 'valu',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'سمبل',
                'code' => 'sympl',
                'active' => true,
                'is_system' => true,
            ],
            [
                'name' => 'سوهولة',
                'code' => 'souhoola',
                'active' => true,
                'is_system' => true,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
