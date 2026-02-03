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
                'logo' => 'cash.png',
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
                'logo' => 'credit_card.png',
            ],
            [
                'name' => 'بطاقة خصم مباشر',
                'code' => 'debit_card',
                'active' => true,
                'is_system' => true,
                'logo' => 'credit_card.png',
            ],
            [
                'name' => 'تحويل بنكي',
                'code' => 'bank_transfer',
                'active' => true,
                'is_system' => true,
                'logo' => 'bank_transfer.png',
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
                'logo' => 'vodafone_cash.png',
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
                'logo' => 'instapay.png',
            ],
            [
                'name' => 'فوري',
                'code' => 'fawry',
                'active' => true,
                'is_system' => true,
                'logo' => 'fawry.png',
            ],

            // خدمات الدفع الإلكتروني
            [
                'name' => 'باي بال',
                'code' => 'paypal',
                'active' => true,
                'is_system' => true,
                'logo' => 'paypal.png',
            ],
            [
                'name' => 'فيزا أونلاين',
                'code' => 'visa_online',
                'active' => true,
                'is_system' => true,
                'logo' => 'credit_card.png',
            ],
            [
                'name' => 'ماستركارد أونلاين',
                'code' => 'mastercard_online',
                'active' => true,
                'is_system' => true,
                'logo' => 'credit_card.png',
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
            $logo = $method['logo'] ?? null;
            unset($method['logo']);

            $paymentMethod = PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );

            if ($logo) {
                $logoPath = "seeders/payment-methods/{$logo}";
                // ونحن نفترض أن الصـور موجودة في storage/app/public/seeders/payment-methods
                \App\Models\Image::updateOrCreate(
                    [
                        'imageable_id' => $paymentMethod->id,
                        'imageable_type' => PaymentMethod::class,
                        'type' => 'logo',
                    ],
                    [
                        'url' => \Illuminate\Support\Facades\Storage::url($logoPath),
                        'is_temp' => false,
                        'company_id' => $paymentMethod->company_id ?? 1,
                        'created_by' => 1,
                    ]
                );
            }
        }
    }
}
